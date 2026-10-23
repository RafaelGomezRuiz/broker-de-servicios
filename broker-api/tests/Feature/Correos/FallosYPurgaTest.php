<?php

namespace Tests\Feature\Correos;

use App\Enums\EstadoCorreo;
use App\Jobs\EnviarCorreo;
use App\Models\Correo;
use App\Services\Correo\TraductorErroresSmtp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class FallosYPurgaTest extends CasoCorreos
{
    /** El servidor SMTP no responde: error técnico, se reintenta. */
    private function servidorCaido(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
        ]);
    }

    /** El servidor rechaza al destinatario: error definitivo, no se reintenta. */
    private function servidorQueRechaza(): void
    {
        Mail::extend('rechazo', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException(
                    'Expected response code "250" but got code "550", with message "550 5.1.10 RESOLVER.ADR.RecipientNotFound".',
                    550,
                );
            }

            public function __toString(): string
            {
                return 'rechazo://';
            }
        });

        config(['mail.mailers.rechazo' => ['transport' => 'rechazo'], 'mail.default' => 'rechazo']);
    }

    public function test_un_error_tecnico_deja_el_correo_en_cola_para_reintentar(): void
    {
        $this->servidorCaido();

        $id = $this->como()->postJson('/v1/correos', $this->correoMinimo())
            ->assertStatus(202)
            ->assertJsonPath('estado', 'en_cola')
            ->assertJsonPath('intentos', 1)
            ->json('id');

        $correo = Correo::find($id);
        $this->assertStringContainsString('MAIL_HOST', $correo->error_mensaje);
        $this->assertStringStartsWith('Reintento en 60 s', $correo->eventos()->get()->last()->detalle);
    }

    public function test_al_agotar_los_intentos_queda_fallido(): void
    {
        $this->servidorCaido();
        \Illuminate\Support\Facades\Queue::fake();

        $id = $this->como()->postJson('/v1/correos', $this->correoMinimo())->json('id');
        Correo::whereKey($id)->update(['intentos' => EnviarCorreo::MAX_INTENTOS - 1]);

        (new EnviarCorreo($id))->handle(app(TraductorErroresSmtp::class));

        $correo = Correo::find($id);
        $this->assertSame(EstadoCorreo::Fallido, $correo->estado);
        $this->assertSame(EnviarCorreo::MAX_INTENTOS, $correo->intentos);
    }

    public function test_un_rechazo_del_servidor_falla_sin_reintentar(): void
    {
        $this->servidorQueRechaza();

        $this->como()->postJson('/v1/correos', $this->correoMinimo())
            ->assertStatus(202)
            ->assertJsonPath('estado', 'fallido')
            ->assertJsonPath('intentos', 1)
            ->assertJsonPath('error', 'El servidor rechazó el destinatario. Revisa la dirección indicada.');
    }

    public function test_un_correo_ya_enviado_no_se_vuelve_a_enviar(): void
    {
        $id = $this->como()->postJson('/v1/correos', $this->correoMinimo())->json('id');

        (new EnviarCorreo($id))->handle(app(TraductorErroresSmtp::class));

        $this->assertCount(1, $this->enviados());
    }

    public function test_la_purga_borra_cuerpos_y_adjuntos_viejos_pero_conserva_la_traza(): void
    {
        $this->servidorQueRechaza(); // fallido: sus adjuntos siguen guardados

        $viejo = $this->como()->post('/v1/correos', $this->correoMinimo([
            'adjuntos' => [UploadedFile::fake()->create('volante.pdf', 10)],
        ]))->json('id');
        $nuevo = $this->como()->postJson('/v1/correos', $this->correoMinimo())->json('id');

        Correo::whereKey($viejo)->update(['created_at' => now()->subDays(91)]);
        $this->assertNotEmpty(Storage::disk('local')->allFiles('adjuntos'));

        $this->artisan('correos:purgar')->assertSuccessful();

        $viejo = Correo::with(['destinatarios', 'adjuntos'])->find($viejo);
        $this->assertNull($viejo->cuerpo_html);
        $this->assertNotNull($viejo->contenido_purgado_at);
        $this->assertSame('Prueba', $viejo->asunto);
        $this->assertSame(['ana@cultura.gob.do'], $viejo->direcciones('para'));
        $this->assertNotNull($viejo->adjuntos->first()->eliminado_at);
        $this->assertEmpty(Storage::disk('local')->allFiles('adjuntos'));

        $this->assertNotNull(Correo::find($nuevo)->cuerpo_html);
    }
}
