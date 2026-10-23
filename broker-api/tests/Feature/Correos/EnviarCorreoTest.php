<?php

namespace Tests\Feature\Correos;

use App\Jobs\EnviarCorreo;
use App\Models\Correo;
use App\Models\CorreoAdjunto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class EnviarCorreoTest extends CasoCorreos
{
    public function test_envia_con_varios_para_cc_y_cco(): void
    {
        $respuesta = $this->como()->postJson('/v1/correos', [
            'para' => ['ana@cultura.gob.do', 'luis@cultura.gob.do'],
            'cc' => ['jefe@cultura.gob.do', 'rrhh@cultura.gob.do'],
            'cco' => ['oculto1@cultura.gob.do', 'oculto2@cultura.gob.do'],
            'asunto' => 'Volante de pago',
            'html' => '<p>Adjunto tu volante</p>',
            'texto' => 'Adjunto tu volante',
            'solicitado_por' => 'rafael.gomez',
        ]);

        $respuesta->assertStatus(202)
            ->assertHeader('Location')
            ->assertJsonPath('estado', 'enviado')
            ->assertJsonPath('cco', ['oculto1@cultura.gob.do', 'oculto2@cultura.gob.do'])
            ->assertJsonPath('solicitado_por', 'rafael.gomez')
            ->assertJsonMissingPath('cuerpo_html');

        $this->assertCount(1, $this->enviados());
        $mensaje = $this->enviados()->first();
        $this->assertSame(['ana@cultura.gob.do', 'luis@cultura.gob.do'], $this->correosDe($mensaje->getTo()));
        $this->assertSame(['jefe@cultura.gob.do', 'rrhh@cultura.gob.do'], $this->correosDe($mensaje->getCc()));
        $this->assertSame(['oculto1@cultura.gob.do', 'oculto2@cultura.gob.do'], $this->correosDe($mensaje->getBcc()));
        $this->assertSame('Volante de pago', $mensaje->getSubject());
        $this->assertSame('<p>Adjunto tu volante</p>', $mensaje->getHtmlBody());
        $this->assertSame('Adjunto tu volante', $mensaje->getTextBody());

        $correo = Correo::find($respuesta->json('id'));
        $this->assertSame($this->sistema->id, $correo->sistema_id);
        $this->assertNotNull($correo->enviado_at);
        $this->assertNotNull($correo->message_id);
    }

    public function test_puede_enviar_solo_con_cco(): void
    {
        $this->como()->postJson('/v1/correos', [
            'cco' => ['todos@cultura.gob.do'],
            'asunto' => 'Felicidades',
            'texto' => 'Feliz cumpleaños',
        ])->assertStatus(202)->assertJsonPath('estado', 'enviado');

        $this->assertSame(['todos@cultura.gob.do'], $this->correosDe($this->enviados()->first()->getBcc()));
    }

    public function test_responde_en_cola_y_encola_en_la_cola_correos(): void
    {
        Queue::fake();

        $this->como()->postJson('/v1/correos', $this->correoMinimo())
            ->assertStatus(202)
            ->assertJsonPath('estado', 'en_cola');

        Queue::assertPushedOn('correos', EnviarCorreo::class);
        $this->assertCount(0, $this->enviados());
    }

    public function test_envia_adjuntos_con_su_nombre_original_y_luego_los_borra(): void
    {
        $respuesta = $this->como()->post('/v1/correos', $this->correoMinimo([
            'adjuntos' => [
                UploadedFile::fake()->create('Volante Octubre 2026.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('anexo.xlsx', 30),
            ],
        ]));

        $respuesta->assertStatus(202)
            ->assertJsonPath('estado', 'enviado')
            ->assertJsonPath('adjuntos.0.nombre', 'Volante Octubre 2026.pdf')
            ->assertJsonPath('adjuntos.0.mime', 'application/pdf')
            ->assertJsonCount(2, 'adjuntos');

        $nombres = collect($this->enviados()->first()->getAttachments())->map->getFilename()->all();
        $this->assertSame(['Volante Octubre 2026.pdf', 'anexo.xlsx'], $nombres);

        // Enviado el correo, los archivos ya no quedan en el broker.
        $adjuntos = CorreoAdjunto::all();
        $this->assertTrue($adjuntos->every(fn ($a) => $a->ruta === null && $a->eliminado_at !== null));
        $this->assertEmpty(Storage::disk('local')->allFiles('adjuntos'));
    }

    public function test_el_nombre_del_adjunto_no_puede_escaparse_de_su_carpeta(): void
    {
        Queue::fake();

        $this->como()->post('/v1/correos', $this->correoMinimo([
            'adjuntos' => [UploadedFile::fake()->create('../../.env', 1)],
        ]))->assertStatus(202);

        $ruta = CorreoAdjunto::first()->ruta;
        $this->assertStringStartsWith('adjuntos/', $ruta);
        $this->assertStringNotContainsString('..', $ruta);
    }

    public function test_valida_en_espanol(): void
    {
        $this->como()->postJson('/v1/correos', ['asunto' => 'Sin nada más'])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Los datos enviados no son válidos.')
            ->assertJsonPath('errores.para.0', 'Indica al menos un destinatario en para, cc o cco.')
            ->assertJsonValidationErrors(['html'], 'errores');

        $errores = $this->como()->postJson('/v1/correos', $this->correoMinimo(['cc' => ['no-es-correo']]))
            ->assertStatus(422)
            ->json('errores');
        $this->assertSame(['El campo cc debe ser un correo válido.'], $errores['cc.0']);

        $this->assertSame(0, Correo::count());
    }

    public function test_limita_el_tamano_total_de_los_adjuntos(): void
    {
        config(['broker.correo.adjunto_max_mb' => 1]);

        $this->como()->post('/v1/correos', $this->correoMinimo([
            'adjuntos' => [
                UploadedFile::fake()->create('a.pdf', 600),
                UploadedFile::fake()->create('b.pdf', 600),
            ],
        ]))->assertStatus(422)
            ->assertJsonPath('errores.adjuntos.0', 'Los adjuntos de un correo no pueden sumar más de 1 MB.');

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_limita_los_destinatarios_por_mensaje(): void
    {
        $muchos = array_map(fn ($i) => "persona{$i}@cultura.gob.do", range(1, 501));

        $this->como()->postJson('/v1/correos', $this->correoMinimo(['para' => [], 'cco' => $muchos]))
            ->assertStatus(422);
    }
}
