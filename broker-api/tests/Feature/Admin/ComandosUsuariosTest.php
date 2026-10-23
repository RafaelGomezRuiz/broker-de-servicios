<?php

namespace Tests\Feature\Admin;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ComandosUsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_pide_la_clave_oculta_y_la_guarda_con_hash(): void
    {
        $this->artisan('usuarios:crear', ['correo' => 'Rafael.Gomez@cultura.gob.do', '--nombre' => 'Rafael Gómez'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'una-clave-larga-y-segura')
            ->expectsQuestion('Repite la contraseña', 'una-clave-larga-y-segura')
            ->assertSuccessful();

        $usuario = Usuario::sole();
        $this->assertSame('rafael.gomez@cultura.gob.do', $usuario->correo);
        $this->assertTrue(Hash::check('una-clave-larga-y-segura', $usuario->password));
    }

    public function test_crear_rechaza_claves_cortas_distintas_o_correos_repetidos(): void
    {
        $this->artisan('usuarios:crear', ['correo' => 'a@cultura.gob.do', '--nombre' => 'A'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'corta')
            ->assertFailed();

        $this->artisan('usuarios:crear', ['correo' => 'a@cultura.gob.do', '--nombre' => 'A'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'una-clave-larga-y-segura')
            ->expectsQuestion('Repite la contraseña', 'otra-clave-larga-distinta')
            ->assertFailed();

        Usuario::factory()->create(['correo' => 'b@cultura.gob.do']);
        $this->artisan('usuarios:crear', ['correo' => 'b@cultura.gob.do', '--nombre' => 'B'])->assertFailed();

        $this->assertSame(1, Usuario::count());
    }

    public function test_cambiar_la_clave_cierra_las_sesiones(): void
    {
        $usuario = Usuario::factory()->create(['correo' => 'c@cultura.gob.do']);
        $this->postJson('/admin/auth/login', ['correo' => 'c@cultura.gob.do', 'password' => 'clave-de-prueba-segura'])->assertOk();

        $this->artisan('usuarios:clave', ['correo' => 'c@cultura.gob.do'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'la-clave-nueva-y-larga')
            ->expectsQuestion('Repite la contraseña', 'la-clave-nueva-y-larga')
            ->assertSuccessful();

        $this->assertSame('cambio_de_clave', $usuario->sesiones()->first()->motivo_revocacion);
        $this->assertTrue(Hash::check('la-clave-nueva-y-larga', $usuario->fresh()->password));
    }

    public function test_listar_y_reactivar(): void
    {
        Usuario::factory()->desactivado()->create(['correo' => 'd@cultura.gob.do']);

        $this->artisan('usuarios:listar')->expectsOutputToContain('desactivado')->assertSuccessful();
        $this->artisan('usuarios:desactivar', ['correo' => 'd@cultura.gob.do', '--reactivar' => true])->assertSuccessful();

        $this->assertTrue(Usuario::sole()->estaActivo());
    }
}
