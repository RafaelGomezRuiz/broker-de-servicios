<?php

namespace Tests\Feature\Admin;

use App\Enums\EstadoCorreo;
use App\Models\CorreoVerificacion;
use App\Models\Sistema;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    private function comoUsuario(): static
    {
        Usuario::factory()->create(['correo' => 'admin@cultura.gob.do']);
        $token = $this->postJson('/admin/auth/login', [
            'correo' => 'admin@cultura.gob.do',
            'password' => 'clave-de-prueba-segura',
        ])->json('access_token');

        return $this->withToken($token);
    }

    public function test_servicios_con_enlaces_a_la_documentacion(): void
    {
        $this->comoUsuario()->getJson('/admin/servicios')
            ->assertOk()
            ->assertJsonPath('data.0.codigo', 'correo')
            ->assertJsonPath('data.0.estado', 'activo')
            ->assertJsonPath('data.0.openapi_url', url('/docs/api.json'))
            ->assertJsonPath('data.1.estado', 'proximamente');
    }

    public function test_sistemas_sin_exponer_llaves(): void
    {
        $sgrh = Sistema::factory()->create(['codigo' => 'sgrh', 'nombre' => 'SGRH']);
        $llave = $sgrh->generarLlave()->plainTextToken;
        $sgrh->correos()->create(['asunto' => 'x', 'estado' => EstadoCorreo::Enviado]);
        Sistema::factory()->suspendido()->create(['codigo' => 'viejo']);

        $respuesta = $this->comoUsuario()->getJson('/admin/sistemas')
            ->assertOk()
            ->assertJsonPath('data.0.codigo', 'sgrh')
            ->assertJsonPath('data.0.llaves_activas', 1)
            ->assertJsonPath('data.0.correos_30_dias', 1)
            ->assertJsonPath('data.1.estado', 'suspendido');

        [, $secreto] = explode('|', $llave, 2);
        $this->assertStringNotContainsString($secreto, $respuesta->getContent());
        $this->assertStringNotContainsString(hash('sha256', $secreto), $respuesta->getContent());
    }

    public function test_estado_de_la_cuenta_de_correo(): void
    {
        $this->comoUsuario()->getJson('/admin/correo/estado')
            ->assertOk()
            ->assertJsonPath('ultima_verificacion', null)
            ->assertJsonPath('correos_24h.enviado', 0);

        CorreoVerificacion::create([
            'exito' => false, 'etapa' => 'autenticacion', 'mensaje' => 'Usuario o contraseña incorrectos.',
            'servidor' => 'smtp.office365.com:587', 'duracion_ms' => 5, 'origen' => 'programada',
        ]);

        $this->getJson('/admin/correo/estado')
            ->assertOk()
            ->assertJsonPath('ultima_verificacion.exito', false)
            ->assertJsonPath('ultima_verificacion.etapa', 'autenticacion')
            ->assertJsonMissingPath('password');
    }

    public function test_cors_solo_para_la_spa_y_con_credenciales(): void
    {
        $preflight = fn (string $origen) => $this->call('OPTIONS', '/admin/auth/refresh', server: [
            'HTTP_ORIGIN' => $origen,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $preflight('http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        // Con un solo origen permitido, la respuesta siempre nombra a la SPA:
        // el navegador de un sitio ajeno ve que no coincide y bloquea la petición.
        $this->assertNotSame(
            'https://sitio-ajeno.com',
            $preflight('https://sitio-ajeno.com')->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
