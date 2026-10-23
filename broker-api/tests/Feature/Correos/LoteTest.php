<?php

namespace Tests\Feature\Correos;

use App\Models\Correo;
use App\Models\CorreoLote;
use Illuminate\Http\UploadedFile;

class LoteTest extends CasoCorreos
{
    private function volantes(int $cantidad): array
    {
        return array_map(fn ($i) => [
            'para' => ["empleado{$i}@cultura.gob.do"],
            'asunto' => "Volante de pago #{$i}",
            'html' => "<p>Volante {$i}</p>",
            'adjuntos' => [UploadedFile::fake()->create("volante-{$i}.pdf", 50, 'application/pdf')],
        ], range(1, $cantidad));
    }

    public function test_cada_correo_del_lote_sale_por_separado_con_su_adjunto(): void
    {
        $respuesta = $this->como()->post('/v1/correos/lote', ['correos' => $this->volantes(3)]);

        $respuesta->assertStatus(202)
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'correos')
            ->assertJsonPath('correos.*.estado', ['enviado', 'enviado', 'enviado']);

        $this->assertCount(3, $this->enviados());
        foreach ($this->enviados()->values() as $i => $mensaje) {
            $n = $i + 1;
            $this->assertSame(["empleado{$n}@cultura.gob.do"], $this->correosDe($mensaje->getTo()));
            $this->assertSame("volante-{$n}.pdf", $mensaje->getAttachments()[0]->getFilename());
        }

        $loteId = $respuesta->json('lote_id');
        $this->como()->getJson("/v1/correos?lote_id={$loteId}")->assertJsonCount(3, 'data');
    }

    public function test_si_un_correo_es_invalido_no_se_encola_ninguno(): void
    {
        $correos = $this->volantes(3);
        $correos[1]['para'] = ['no-es-correo'];

        $this->como()->post('/v1/correos/lote', ['correos' => $correos])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['correos.1.para.0'], 'errores');

        $this->assertSame(0, Correo::count());
        $this->assertSame(0, CorreoLote::count());
        $this->assertCount(0, $this->enviados());
    }

    public function test_el_lote_es_idempotente(): void
    {
        $cabecera = ['Idempotency-Key' => 'volantes-octubre'];
        $datos = ['correos' => [
            ['para' => ['a@cultura.gob.do'], 'asunto' => 'A', 'texto' => 'a'],
            ['para' => ['b@cultura.gob.do'], 'asunto' => 'B', 'texto' => 'b'],
        ]];

        $primero = $this->como(null, $cabecera)->postJson('/v1/correos/lote', $datos)->assertStatus(202);
        $this->como(null, $cabecera)->postJson('/v1/correos/lote', $datos)
            ->assertStatus(202)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('lote_id', $primero->json('lote_id'));

        $this->assertSame(2, Correo::count());
        $this->assertCount(2, $this->enviados());
    }

    public function test_un_lote_vacio_o_demasiado_grande_se_rechaza(): void
    {
        $this->como()->postJson('/v1/correos/lote', ['correos' => []])->assertStatus(422);

        $muchos = array_fill(0, 201, ['para' => ['a@cultura.gob.do'], 'asunto' => 'A', 'texto' => 'a']);
        $this->como()->postJson('/v1/correos/lote', ['correos' => $muchos])->assertStatus(422);
    }
}
