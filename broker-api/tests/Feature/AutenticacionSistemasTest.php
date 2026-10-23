<?php

namespace Tests\Feature;

use App\Models\Sistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutenticacionSistemasTest extends TestCase
{
    use RefreshDatabase;

    private function conLlave(string $llave): static
    {
        // En una prueba la aplicación vive entre peticiones y el guard recuerda
        // al último sistema autenticado; en producción cada petición empieza de cero.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$llave);
    }

    public function test_sin_llave_responde_401_en_json(): void
    {
        $this->getJson('/v1/sistema')
            ->assertUnauthorized()
            ->assertJsonStructure(['mensaje']);
    }

    public function test_sin_llave_y_sin_accept_json_tambien_responde_401_en_json(): void
    {
        $this->get('/v1/sistema')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_una_llave_inventada_responde_401(): void
    {
        $this->conLlave('1|brk_inventada')->getJson('/v1/sistema')->assertUnauthorized();
    }

    public function test_una_llave_valida_identifica_al_sistema(): void
    {
        $sistema = Sistema::factory()->create(['codigo' => 'sgrh', 'nombre' => 'SGRH']);
        $llave = $sistema->generarLlave()->plainTextToken;

        $this->conLlave($llave)->getJson('/v1/sistema')
            ->assertOk()
            ->assertExactJson(['codigo' => 'sgrh', 'nombre' => 'SGRH', 'llave' => 'principal']);
    }

    public function test_la_llave_tiene_prefijo_y_en_la_base_solo_queda_su_hash(): void
    {
        $llave = Sistema::factory()->create()->generarLlave()->plainTextToken;

        $this->assertMatchesRegularExpression('/^\d+\|brk_\w+$/', $llave);

        [, $secreto] = explode('|', $llave, 2);
        $guardado = DB::table('personal_access_tokens')->value('token');
        $this->assertNotSame($secreto, $guardado);
        $this->assertSame(hash('sha256', $secreto), $guardado);
    }

    public function test_registra_el_ultimo_uso_de_la_llave(): void
    {
        $sistema = Sistema::factory()->create();
        $llave = $sistema->generarLlave();
        $this->assertNull($llave->accessToken->fresh()->last_used_at);

        $this->conLlave($llave->plainTextToken)->getJson('/v1/sistema')->assertOk();

        $this->assertNotNull($llave->accessToken->fresh()->last_used_at);
    }

    public function test_una_llave_revocada_deja_de_funcionar(): void
    {
        $llave = Sistema::factory()->create()->generarLlave();
        $llave->accessToken->delete();

        $this->conLlave($llave->plainTextToken)->getJson('/v1/sistema')->assertUnauthorized();
    }

    public function test_un_sistema_suspendido_no_puede_usar_sus_llaves(): void
    {
        $llave = Sistema::factory()->suspendido()->create()->generarLlave()->plainTextToken;

        $this->conLlave($llave)->getJson('/v1/sistema')->assertUnauthorized();
    }

    public function test_el_limite_de_peticiones_es_por_sistema(): void
    {
        config(['broker.sistemas.peticiones_por_minuto' => 2]);
        $llaveA = Sistema::factory()->create()->generarLlave()->plainTextToken;
        $llaveB = Sistema::factory()->create()->generarLlave()->plainTextToken;

        $this->conLlave($llaveA)->getJson('/v1/sistema')->assertOk();
        $this->conLlave($llaveA)->getJson('/v1/sistema')->assertOk();
        $this->conLlave($llaveA)->getJson('/v1/sistema')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonStructure(['mensaje']);

        // Otro sistema no se ve afectado por el límite del primero.
        $this->conLlave($llaveB)->getJson('/v1/sistema')->assertOk();
    }
}
