<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoCorreo;
use App\Http\Controllers\Controller;
use App\Models\Correo;
use App\Models\CorreoVerificacion;
use App\Models\Sistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Lo que muestra la SPA: servicios, sistemas integrados y estado de la
 * cuenta de correo. Solo lectura; nunca devuelve llaves ni contraseñas.
 */
class PanelController extends Controller
{
    public function servicios(): JsonResponse
    {
        $servicios = collect(config('broker.servicios'))->map(fn (array $s) => $s + [
            'documentacion_url' => isset($s['documentacion']) ? url($s['documentacion']) : null,
            'openapi_url' => isset($s['openapi']) ? url($s['openapi']) : null,
        ])->map(fn (array $s) => collect($s)->except(['documentacion', 'openapi'])->all());

        return response()->json(['data' => $servicios->values()]);
    }

    public function sistemas(): JsonResponse
    {
        $desde = now()->subDays(30);

        $sistemas = Sistema::query()
            ->withCount('tokens')
            ->withMax('tokens', 'last_used_at')
            ->withCount(['correos as correos_30_dias' => fn ($q) => $q->where('created_at', '>=', $desde)])
            ->orderBy('codigo')
            ->get()
            ->map(fn (Sistema $s) => [
                'codigo' => $s->codigo,
                'nombre' => $s->nombre,
                'responsable' => $s->responsable,
                'estado' => $s->estaActivo() ? 'activo' : 'suspendido',
                'llaves_activas' => $s->tokens_count,
                'ultimo_uso_at' => $s->tokens_max_last_used_at
                    ? Carbon::parse($s->tokens_max_last_used_at)->toIso8601String()
                    : null,
                'correos_30_dias' => $s->correos_30_dias,
                'creado_at' => $s->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $sistemas]);
    }

    public function estadoCorreo(): JsonResponse
    {
        $smtp = config('mail.mailers.smtp');
        $ultima = CorreoVerificacion::ultima();

        $ultimas24h = Correo::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return response()->json([
            'servidor' => $smtp['host'].':'.$smtp['port'],
            'usuario' => $smtp['username'] ?: null,
            'remitente' => config('mail.from.address'),
            'ultima_verificacion' => $ultima ? [
                'exito' => $ultima->exito,
                'etapa' => $ultima->etapa,
                'mensaje' => $ultima->mensaje,
                'origen' => $ultima->origen,
                'fecha' => $ultima->created_at->toIso8601String(),
            ] : null,
            'correos_24h' => collect(EstadoCorreo::cases())
                ->mapWithKeys(fn (EstadoCorreo $e) => [$e->value => (int) ($ultimas24h[$e->value] ?? 0)]),
        ]);
    }
}
