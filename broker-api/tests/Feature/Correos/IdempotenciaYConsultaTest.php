<?php

namespace Tests\Feature\Correos;

use App\Models\Correo;
use App\Models\Sistema;

class IdempotenciaYConsultaTest extends CasoCorreos
{
    public function test_la_misma_idempotency_key_no_envia_dos_veces(): void
    {
        $cabecera = ['Idempotency-Key' => 'volante-2026-10-4471'];

        $primero = $this->como(null, $cabecera)->postJson('/v1/correos', $this->correoMinimo())->assertStatus(202);
        $segundo = $this->como(null, $cabecera)->postJson('/v1/correos', $this->correoMinimo())
            ->assertStatus(202)
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($primero->json('id'), $segundo->json('id'));
        $this->assertSame(1, Correo::count());
        $this->assertCount(1, $this->enviados());
    }

    public function test_la_misma_llave_con_otro_contenido_se_rechaza(): void
    {
        $cabecera = ['Idempotency-Key' => 'llave-1'];

        $this->como(null, $cabecera)->postJson('/v1/correos', $this->correoMinimo())->assertStatus(202);
        $this->como(null, $cabecera)->postJson('/v1/correos', $this->correoMinimo(['asunto' => 'Otro']))
            ->assertStatus(422)
            ->assertJsonStructure(['mensaje']);

        $this->assertSame(1, Correo::count());
    }

    public function test_la_llave_de_idempotencia_es_por_sistema(): void
    {
        $otraLlave = Sistema::factory()->create()->generarLlave()->plainTextToken;
        $cabecera = ['Idempotency-Key' => 'misma'];

        $this->como(null, $cabecera)->postJson('/v1/correos', $this->correoMinimo())->assertStatus(202);
        $this->como($otraLlave, $cabecera)->postJson('/v1/correos', $this->correoMinimo())->assertStatus(202);

        $this->assertSame(2, Correo::count());
    }

    public function test_consultar_un_correo_muestra_su_historial(): void
    {
        $id = $this->como()->postJson('/v1/correos', $this->correoMinimo())->json('id');

        $this->como()->getJson("/v1/correos/{$id}")
            ->assertOk()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('eventos.*.estado', ['en_cola', 'enviando', 'enviado']);
    }

    public function test_un_sistema_no_ve_los_correos_de_otro(): void
    {
        $id = $this->como()->postJson('/v1/correos', $this->correoMinimo())->json('id');
        $otraLlave = Sistema::factory()->create()->generarLlave()->plainTextToken;

        $this->como($otraLlave)->getJson("/v1/correos/{$id}")
            ->assertNotFound()
            ->assertExactJson(['mensaje' => 'No encontrado.']);

        $this->como($otraLlave)->getJson('/v1/correos')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_listado_con_filtros_y_paginacion(): void
    {
        $this->como()->postJson('/v1/correos', $this->correoMinimo(['para' => ['ana@cultura.gob.do']]));
        $this->como()->postJson('/v1/correos', $this->correoMinimo(['para' => ['luis@cultura.gob.do'], 'solicitado_por' => 'maria']));
        $this->como()->postJson('/v1/correos', $this->correoMinimo(['para' => ['Luis@Cultura.gob.do']]));

        $this->como()->getJson('/v1/correos?destinatario=luis@cultura.gob.do')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->como()->getJson('/v1/correos?solicitado_por=maria')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->como()->getJson('/v1/correos?estado=fallido')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->como()->getJson('/v1/correos?por_pagina=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);

        $this->como()->getJson('/v1/correos?estado=inventado')->assertStatus(422);
    }
}
