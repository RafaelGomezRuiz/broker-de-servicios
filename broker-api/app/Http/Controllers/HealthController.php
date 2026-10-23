<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Estado del servicio para las sondas de Kubernetes.
 *
 * Responde 200 si la API puede hablar con la base de datos y 503 si no.
 * El detalle del error va al log, nunca a la respuesta: el endpoint es
 * público y no debe revelar hosts ni credenciales.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            $baseDeDatos = 'ok';
        } catch (Throwable $e) {
            Log::error('Health: la base de datos no responde', ['error' => $e->getMessage()]);
            $baseDeDatos = 'error';
        }

        $ok = $baseDeDatos === 'ok';

        return response()->json([
            'estado' => $ok ? 'ok' : 'degradado',
            'servicio' => 'broker-api',
            'base_de_datos' => $baseDeDatos,
            'hora' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
