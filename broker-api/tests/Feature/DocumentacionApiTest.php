<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La documentación se genera del código: estas pruebas avisan si un cambio
 * la deja diciendo algo distinto de lo que la API hace.
 */
class DocumentacionApiTest extends TestCase
{
    private function documento(): array
    {
        return $this->getJson('/docs/api.json')->assertOk()->json();
    }

    public function test_la_pagina_y_el_contrato_son_publicos(): void
    {
        $this->get('/docs/api')->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $this->getJson('/docs/api.json')->assertOk();
    }

    public function test_solo_documenta_la_api_de_los_sistemas(): void
    {
        $doc = $this->documento();

        $this->assertEqualsCanonicalizing(
            ['/sistema', '/correos', '/correos/lote', '/correos/{id}'],
            array_keys($doc['paths']),
        );
        $this->assertStringEndsWith('/v1', $doc['servers'][0]['url']);
        $this->assertSame('bearer', $doc['components']['securitySchemes']['http']['scheme']);
        $this->assertStringContainsString('No significa que llegó al buzón', $doc['info']['description']);
    }

    public function test_los_envios_responden_202_y_aceptan_idempotency_key(): void
    {
        $doc = $this->documento();

        foreach (['/correos', '/correos/lote'] as $ruta) {
            $operacion = $doc['paths'][$ruta]['post'];
            $this->assertArrayHasKey('202', $operacion['responses'], $ruta);
            $this->assertArrayNotHasKey('200', $operacion['responses'], $ruta);
            $this->assertContains('Idempotency-Key', array_column($operacion['parameters'], 'name'), $ruta);
        }

        $this->assertArrayHasKey('404', $doc['paths']['/correos/{id}']['get']['responses']);
    }

    public function test_los_errores_se_documentan_con_el_formato_real(): void
    {
        $respuestas = $this->documento()['components']['responses'];

        $validacion = $respuestas['ValidationException']['content']['application/json']['schema'];
        $this->assertSame(['mensaje', 'errores'], $validacion['required']);

        $autenticacion = $respuestas['AuthenticationException']['content']['application/json']['schema'];
        $this->assertSame(['mensaje'], $autenticacion['required']);
    }
}
