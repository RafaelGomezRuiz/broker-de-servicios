<?php

namespace Tests\Feature\Admin;

use App\Models\Sesion;
use App\Models\Sistema;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class SesionSpaTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'broker_refresh';

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = Usuario::factory()->create(['correo' => 'rafael.gomez@cultura.gob.do']);
    }

    private function entrar(): TestResponse
    {
        return $this->postJson('/admin/auth/login', [
            'correo' => 'rafael.gomez@cultura.gob.do',
            'password' => 'clave-de-prueba-segura',
        ]);
    }

    private function refreshDe(TestResponse $respuesta): ?string
    {
        return $respuesta->getCookie(self::COOKIE, false)?->getValue();
    }

    /** Cada petición empieza de cero, como en producción. */
    private function limpio(): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        // Sin esto, las peticiones JSON de prueba no envían cookies (el navegador sí lo hace con credentials).
        $this->withCredentials();

        return $this;
    }

    private function renovar(?string $refresh): TestResponse
    {
        return $this->limpio()->withUnencryptedCookie(self::COOKIE, (string) $refresh)->postJson('/admin/auth/refresh');
    }

    private function conAcceso(string $accessToken): static
    {
        return $this->limpio()->withToken($accessToken);
    }

    public function test_login_entrega_access_token_y_cookie_httponly(): void
    {
        $respuesta = $this->limpio()->entrar()
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expira_en', 'usuario' => ['id', 'nombre', 'correo']])
            ->assertJsonPath('expira_en', 15 * 60)
            ->assertJsonMissingPath('refresh_token')
            ->assertHeader('Cache-Control', 'no-store, private');

        $cookie = $respuesta->getCookie(self::COOKIE, false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/admin/auth', $cookie->getPath());
        $this->assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        $this->assertSame(64, strlen($cookie->getValue()));

        // En la base solo queda el hash.
        $this->assertSame(hash('sha256', $cookie->getValue()), Sesion::first()->token_hash);
        $this->assertNotNull($this->usuario->fresh()->ultimo_acceso_at);
    }

    public function test_credenciales_incorrectas_o_usuario_desactivado_dan_el_mismo_mensaje(): void
    {
        $mensaje = 'Correo o contraseña incorrectos.';

        $this->postJson('/admin/auth/login', ['correo' => 'rafael.gomez@cultura.gob.do', 'password' => 'otra'])
            ->assertUnauthorized()->assertExactJson(['mensaje' => $mensaje]);

        $this->limpio()->postJson('/admin/auth/login', ['correo' => 'nadie@cultura.gob.do', 'password' => 'x'])
            ->assertUnauthorized()->assertExactJson(['mensaje' => $mensaje]);

        $this->usuario->forceFill(['desactivado_at' => now()])->save();
        $this->limpio()->entrar()->assertUnauthorized()->assertExactJson(['mensaje' => $mensaje]);

        $this->assertSame(0, Sesion::count());
    }

    public function test_bloquea_tras_cinco_intentos_fallidos(): void
    {
        foreach (range(1, 5) as $_) {
            $this->limpio()->postJson('/admin/auth/login', ['correo' => 'rafael.gomez@cultura.gob.do', 'password' => 'mala'])
                ->assertUnauthorized();
        }

        // Ni siquiera la clave correcta entra durante el bloqueo.
        $this->limpio()->entrar()->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_el_access_token_da_acceso_al_panel(): void
    {
        $token = $this->entrar()->json('access_token');

        $this->conAcceso($token)->getJson('/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('correo', 'rafael.gomez@cultura.gob.do');
    }

    public function test_sin_token_el_panel_responde_401_hablando_de_la_sesion(): void
    {
        $this->getJson('/admin/servicios')
            ->assertUnauthorized()
            ->assertExactJson(['mensaje' => 'Sesión no válida o vencida. Vuelve a entrar.']);
    }

    public function test_una_llave_de_sistema_no_abre_el_panel_ni_un_jwt_abre_v1(): void
    {
        $llave = Sistema::factory()->create()->generarLlave()->plainTextToken;
        $this->conAcceso($llave)->getJson('/admin/servicios')->assertUnauthorized();

        $jwt = $this->limpio()->entrar()->json('access_token');
        $this->conAcceso($jwt)->getJson('/v1/sistema')->assertUnauthorized();
    }

    public function test_refresh_rota_la_cookie_y_entrega_un_access_token_nuevo(): void
    {
        $primera = $this->refreshDe($this->entrar());

        $respuesta = $this->renovar($primera)->assertOk()->assertJsonStructure(['access_token', 'expira_en']);
        $segunda = $this->refreshDe($respuesta);

        $this->assertNotNull($segunda);
        $this->assertNotSame($primera, $segunda);
        $this->renovar($segunda)->assertOk();
    }

    public function test_dos_pestanas_que_renuevan_a_la_vez_no_cierran_la_sesion(): void
    {
        $original = $this->refreshDe($this->entrar());
        $this->renovar($original)->assertOk();

        // La otra pestaña llega con la cookie anterior dentro del margen.
        $respuesta = $this->renovar($original)->assertOk();
        $this->assertNull($this->refreshDe($respuesta), 'Dentro del margen no se vuelve a rotar.');
        $this->assertNull(Sesion::first()->revocada_at);
    }

    public function test_reutilizar_un_refresh_viejo_revoca_la_sesion(): void
    {
        $original = $this->refreshDe($this->entrar());
        $nueva = $this->refreshDe($this->renovar($original));

        $this->travel(31)->seconds();

        $this->renovar($original)->assertUnauthorized();
        $this->assertSame('reutilizacion', Sesion::first()->motivo_revocacion);

        // Con la sesión revocada, tampoco sirve la cookie legítima.
        $this->renovar($nueva)->assertUnauthorized();
    }

    public function test_la_sesion_vence_por_inactividad(): void
    {
        $refresh = $this->refreshDe($this->entrar());

        $this->travel(31)->minutes();

        $this->renovar($refresh)
            ->assertUnauthorized()
            ->assertExactJson(['mensaje' => 'La sesión venció. Vuelve a entrar.']);
    }

    public function test_usar_el_panel_mantiene_viva_la_sesion(): void
    {
        $respuesta = $this->entrar();
        $refresh = $this->refreshDe($respuesta);

        // Cada 10 minutos: renovar el access token y usar el panel.
        foreach (range(1, 4) as $_) {
            $this->travel(10)->minutes();
            $renovada = $this->renovar($refresh)->assertOk();
            $refresh = $this->refreshDe($renovada);
            $this->conAcceso($renovada->json('access_token'))->getJson('/admin/servicios')->assertOk();
        }
    }

    public function test_la_sesion_vence_al_cumplir_su_vida_maxima_aunque_se_use(): void
    {
        $refresh = $this->refreshDe($this->entrar());

        // 480 minutos de vida máxima: renovando cada 20 (sin caer por inactividad)
        // se llega a 460 minutos con la sesión viva...
        foreach (range(1, 23) as $_) {
            $this->travel(20)->minutes();
            $refresh = $this->refreshDe($this->renovar($refresh)->assertOk());
        }

        // ...y pasados los 480, ya no se puede renovar.
        $this->travel(21)->minutes();
        $this->renovar($refresh)->assertUnauthorized();
    }

    public function test_al_abrir_la_spa_sin_cookie_responde_401(): void
    {
        $this->postJson('/admin/auth/refresh')->assertUnauthorized();
    }

    public function test_logout_corta_el_refresh_y_el_access_token_al_instante(): void
    {
        $login = $this->entrar();
        $token = $login->json('access_token');
        $refresh = $this->refreshDe($login);

        $salida = $this->limpio()
            ->withToken($token)
            ->withUnencryptedCookie(self::COOKIE, $refresh)
            ->postJson('/admin/auth/logout')
            ->assertNoContent();
        $this->assertSame('', $this->refreshDe($salida), 'La cookie se borra.');

        $this->renovar($refresh)->assertUnauthorized();
        $this->conAcceso($token)->getJson('/admin/auth/me')->assertUnauthorized();
        $this->assertSame('logout', Sesion::first()->motivo_revocacion);
    }

    public function test_logout_funciona_aunque_el_access_token_haya_vencido(): void
    {
        $refresh = $this->refreshDe($this->entrar());
        $this->travel(20)->minutes();

        $this->limpio()->withUnencryptedCookie(self::COOKIE, $refresh)->postJson('/admin/auth/logout')->assertNoContent();

        $this->assertNotNull(Sesion::first()->revocada_at);
    }

    public function test_desactivar_al_usuario_corta_su_acceso_al_instante(): void
    {
        $token = $this->entrar()->json('access_token');

        $this->artisan('usuarios:desactivar', ['correo' => 'rafael.gomez@cultura.gob.do'])->assertSuccessful();

        $this->conAcceso($token)->getJson('/admin/servicios')->assertUnauthorized();
    }
}
