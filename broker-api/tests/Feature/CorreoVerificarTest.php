<?php

namespace Tests\Feature;

use App\Models\CorreoVerificacion;
use App\Services\Correo\VerificadorSmtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorreoVerificarTest extends TestCase
{
    use RefreshDatabase;

    /** Apunta la cuenta a un puerto local donde no escucha nadie. */
    private function servidorInalcanzable(): void
    {
        config([
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 3,
        ]);
    }

    public function test_un_servidor_inalcanzable_queda_registrado_como_fallo_de_conexion(): void
    {
        $this->servidorInalcanzable();

        $this->artisan('correo:verificar')
            ->expectsOutputToContain('Revisa MAIL_HOST y MAIL_PORT')
            ->assertFailed();

        $verificacion = CorreoVerificacion::ultima();
        $this->assertFalse($verificacion->exito);
        $this->assertSame('conexion', $verificacion->etapa);
        $this->assertSame('127.0.0.1:1', $verificacion->servidor);
        $this->assertSame('manual', $verificacion->origen);
        $this->assertNotEmpty($verificacion->detalle);
    }

    public function test_la_verificacion_programada_se_marca_como_tal(): void
    {
        $this->servidorInalcanzable();

        $this->artisan('correo:verificar', ['--programada' => true])->assertFailed();

        $this->assertSame('programada', CorreoVerificacion::ultima()->origen);
    }

    public function test_muestra_el_exito_y_no_expone_la_contrasena(): void
    {
        config(['mail.mailers.smtp.password' => 'clave-super-secreta']);

        $this->mock(VerificadorSmtp::class)
            ->shouldReceive('verificar')->once()->with('ana@cultura.gob.do', 'manual')
            ->andReturn(new CorreoVerificacion([
                'exito' => true,
                'etapa' => 'completa',
                'mensaje' => 'Conexión e inicio de sesión correctos; correo de prueba enviado a ana@cultura.gob.do.',
                'servidor' => '127.0.0.1:1025',
                'duracion_ms' => 12,
                'origen' => 'manual',
            ]));

        $this->artisan('correo:verificar', ['--enviar-prueba' => 'ana@cultura.gob.do'])
            ->expectsOutputToContain('correo de prueba enviado a ana@cultura.gob.do')
            ->doesntExpectOutputToContain('clave-super-secreta')
            ->assertSuccessful();
    }

    public function test_rechaza_un_destinatario_de_prueba_invalido_sin_conectar(): void
    {
        $this->mock(VerificadorSmtp::class)->shouldNotReceive('verificar');

        $this->artisan('correo:verificar', ['--enviar-prueba' => 'no-es-correo'])->assertFailed();

        $this->assertSame(0, CorreoVerificacion::count());
    }

    public function test_borra_el_historial_de_mas_de_30_dias(): void
    {
        $this->servidorInalcanzable();
        $vieja = CorreoVerificacion::create([
            'exito' => true, 'etapa' => 'completa', 'mensaje' => 'ok', 'servidor' => 'x:1',
            'duracion_ms' => 1, 'origen' => 'programada',
        ]);
        $vieja->forceFill(['created_at' => now()->subDays(31)])->save();

        $this->artisan('correo:verificar')->assertFailed();

        $this->assertModelMissing($vieja);
        $this->assertSame(1, CorreoVerificacion::count());
    }

    public function test_el_scheduler_verifica_cada_hora(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('correo:verificar --programada')
            ->assertSuccessful();
    }
}
