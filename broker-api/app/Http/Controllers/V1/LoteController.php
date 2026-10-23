<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\EnviarLoteRequest;
use App\Jobs\EnviarCorreo;
use App\Models\Correo;
use App\Models\CorreoLote;
use App\Models\Sistema;
use App\Services\Correo\RegistroCorreos;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Envío masivo (volantes, recordatorios): un mensaje individual por cada
 * elemento de «correos». Todo o nada: si un correo no es válido, no se
 * encola ninguno.
 */
#[Group('Correos', weight: 1)]
class LoteController extends Controller
{
    /**
     * Enviar un lote
     *
     * Para envíos masivos (volantes, recordatorios): cada elemento de `correos` es un
     * correo individual, con sus propios destinatarios, cuerpo y adjuntos, y los mismos
     * campos que `POST /v1/correos`. Hasta 200 por petición; para más, envía varios lotes.
     *
     * **Todo o nada:** si un correo no es válido, no se encola ninguno y el error indica
     * cuál (p. ej. `correos.3.para.0`). Con adjuntos, en multipart los campos se nombran
     * `correos[0][para][]`, `correos[0][adjuntos][]`, etc.
     *
     * Sigue cada correo con `GET /v1/correos?lote_id=...`.
     */
    #[HeaderParameter('Idempotency-Key', 'Llave única de esta petición (máx. 255). Si se repite con el mismo contenido, devuelve lo creado la primera vez con la cabecera `Idempotent-Replayed: true` y no envía nada nuevo; con otro contenido responde 422. Recomendada siempre.', required: false, type: 'string', example: 'volante-2026-10-4471')]
    public function store(EnviarLoteRequest $request, RegistroCorreos $registro): JsonResponse
    {
        /** @var Sistema $sistema */
        $sistema = $request->user();
        $correos = $request->validated('correos');
        $llave = $request->header('Idempotency-Key');

        $adjuntosDe = fn (int $i) => $request->file("correos.{$i}.adjuntos", []);
        $huella = $llave ? hash('sha256', implode('|', array_map(
            fn ($i) => RegistroCorreos::huella($correos[$i], $adjuntosDe($i)),
            array_keys($correos),
        ))) : null;

        if ($llave && ($repetido = $this->repetido($sistema, $llave, $huella))) {
            return $repetido;
        }

        $creados = [];
        try {
            $lote = DB::transaction(function () use ($registro, $sistema, $correos, $adjuntosDe, $llave, $huella, &$creados) {
                $lote = CorreoLote::create([
                    'sistema_id' => $sistema->id,
                    'idempotency_key' => $llave,
                    'idempotency_hash' => $huella,
                    'total' => count($correos),
                ]);

                foreach (array_keys($correos) as $i) {
                    $creados[] = $registro->registrar($sistema, $correos[$i], $adjuntosDe($i), $lote)->id;
                }

                return $lote;
            });
        } catch (UniqueConstraintViolationException $e) {
            array_map($registro->eliminarArchivos(...), $creados);

            return $this->repetido($sistema, $llave, $huella) ?? throw $e;
        } catch (Throwable $e) {
            array_map($registro->eliminarArchivos(...), $creados);
            throw $e;
        }

        foreach ($creados as $id) {
            EnviarCorreo::dispatch($id);
        }

        /**
         * Lote recibido: todos sus correos quedan encolados.
         *
         * @status 202
         *
         * @body array{lote_id: string, total: int, correos: list<array{id: string, estado: string}>}
         */
        return $this->respuesta($lote, 202);
    }

    private function repetido(Sistema $sistema, string $llave, ?string $huella): ?JsonResponse
    {
        $existente = CorreoLote::where('sistema_id', $sistema->id)->where('idempotency_key', $llave)->first();
        if (! $existente) {
            return null;
        }

        if ($existente->idempotency_hash !== $huella) {
            return response()->json([
                'mensaje' => 'Esta Idempotency-Key ya se usó con un lote distinto. Usa una llave nueva para un lote nuevo.',
            ], 422);
        }

        return $this->respuesta($existente, 202)->header('Idempotent-Replayed', 'true');
    }

    private function respuesta(CorreoLote $lote, int $estado): JsonResponse
    {
        return response()->json([
            'lote_id' => $lote->id,
            'total' => $lote->total,
            'correos' => $lote->correos()->get(['id', 'estado'])->map(fn (Correo $c) => [
                'id' => $c->id,
                'estado' => $c->estado->value,
            ]),
        ], $estado)->header('Location', url("/v1/correos?lote_id={$lote->id}"));
    }
}
