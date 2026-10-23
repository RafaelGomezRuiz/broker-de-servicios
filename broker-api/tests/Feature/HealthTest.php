<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_responde_ok_cuando_la_base_de_datos_funciona(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJson([
                'estado' => 'ok',
                'servicio' => 'broker-api',
                'base_de_datos' => 'ok',
            ]);
    }

    public function test_responde_503_sin_detalles_cuando_la_base_de_datos_falla(): void
    {
        DB::shouldReceive('select')->andThrow(new \RuntimeException('SQLSTATE secreto en host-interno'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJson(['estado' => 'degradado', 'base_de_datos' => 'error'])
            ->assertDontSee('host-interno');
    }

    public function test_las_rutas_inexistentes_responden_json(): void
    {
        $this->get('/no-existe')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
