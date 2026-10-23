<?php

namespace Tests\Feature;

use App\Models\Sistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ComandosSistemasTest extends TestCase
{
    use RefreshDatabase;

    /** Ejecuta un comando y devuelve la llave que imprimió. */
    private function llaveImpresa(string $comando, array $parametros): string
    {
        $codigo = Artisan::call($comando, $parametros);
        // output() vacía el buffer al leerlo: se lee una sola vez.
        $salida = Artisan::output();
        $this->assertSame(0, $codigo, $salida);

        preg_match('/\d+\|brk_\w+/', $salida, $m);
        $this->assertNotEmpty($m, 'El comando no imprimió una llave: '.$salida);

        return $m[0];
    }

    private function funciona(string $llave): bool
    {
        // En una prueba la aplicación vive entre peticiones y el guard recuerda
        // al último sistema autenticado; en producción cada petición empieza de cero.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$llave)
            ->getJson('/v1/sistema')
            ->isOk();
    }

    public function test_crear_registra_el_sistema_y_su_llave_funciona(): void
    {
        $llave = $this->llaveImpresa('sistemas:crear', [
            'codigo' => 'sgrh',
            '--nombre' => 'Sistema de Gestión de RRHH',
            '--responsable' => 'rafael.gomez@cultura.gob.do',
        ]);

        $this->assertDatabaseHas('sistemas', ['codigo' => 'sgrh', 'responsable' => 'rafael.gomez@cultura.gob.do']);
        $this->assertTrue($this->funciona($llave));
    }

    public function test_crear_rechaza_codigos_repetidos_o_invalidos(): void
    {
        Sistema::factory()->create(['codigo' => 'sgrh']);

        $this->artisan('sistemas:crear', ['codigo' => 'sgrh', '--nombre' => 'Otro'])->assertFailed();
        $this->artisan('sistemas:crear', ['codigo' => 'Con Espacios', '--nombre' => 'X'])->assertFailed();
        $this->artisan('sistemas:crear', ['codigo' => 'ok', '--nombre' => 'X', '--responsable' => 'no-es-correo'])->assertFailed();

        $this->assertSame(1, Sistema::count());
    }

    public function test_nueva_llave_convive_con_la_anterior(): void
    {
        $primera = $this->llaveImpresa('sistemas:crear', ['codigo' => 'sic', '--nombre' => 'SIC']);
        $segunda = $this->llaveImpresa('sistemas:nueva-llave', ['codigo' => 'sic']);

        $this->assertNotSame($primera, $segunda);
        $this->assertTrue($this->funciona($primera));
        $this->assertTrue($this->funciona($segunda));
    }

    public function test_revocar_llave_la_desactiva_y_no_toca_otros_sistemas(): void
    {
        $sgrh = Sistema::factory()->create(['codigo' => 'sgrh']);
        $llaveSgrh = $sgrh->generarLlave();
        $otro = Sistema::factory()->create(['codigo' => 'otro']);
        $llaveOtro = $otro->generarLlave();

        // El ID de una llave de otro sistema no se puede revocar desde sgrh.
        $this->artisan('sistemas:revocar-llave', ['codigo' => 'sgrh', 'llave' => $llaveOtro->accessToken->id, '--force' => true])
            ->assertFailed();
        $this->assertTrue($this->funciona($llaveOtro->plainTextToken));

        $this->artisan('sistemas:revocar-llave', ['codigo' => 'sgrh', 'llave' => $llaveSgrh->accessToken->id, '--force' => true])
            ->assertSuccessful();
        $this->assertFalse($this->funciona($llaveSgrh->plainTextToken));
    }

    public function test_suspender_y_reactivar(): void
    {
        $llave = Sistema::factory()->create(['codigo' => 'festival'])->generarLlave()->plainTextToken;

        $this->artisan('sistemas:suspender', ['codigo' => 'festival'])->assertSuccessful();
        $this->assertFalse($this->funciona($llave));

        $this->artisan('sistemas:suspender', ['codigo' => 'festival', '--reactivar' => true])->assertSuccessful();
        $this->assertTrue($this->funciona($llave));
    }

    public function test_listar_funciona_con_y_sin_codigo(): void
    {
        Sistema::factory()->create(['codigo' => 'sgrh'])->generarLlave();

        $this->artisan('sistemas:listar')->expectsOutputToContain('sgrh')->assertSuccessful();
        $this->artisan('sistemas:listar', ['codigo' => 'sgrh'])->expectsOutputToContain('principal')->assertSuccessful();
        $this->artisan('sistemas:listar', ['codigo' => 'no-existe'])->assertFailed();
    }

    public function test_el_seeder_crea_el_sistema_de_pruebas_una_sola_vez(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, Sistema::where('codigo', 'pruebas')->count());
        $this->assertSame(1, Sistema::firstWhere('codigo', 'pruebas')->tokens()->count());
    }
}
