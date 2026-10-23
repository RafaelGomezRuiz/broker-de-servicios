<?php

namespace App\Http\Controllers\V1;

use App\Enums\EstadoCorreo;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\EnviarCorreoRequest;
use App\Http\Resources\CorreoResource;
use App\Jobs\EnviarCorreo;
use App\Models\Correo;
use App\Models\Sistema;
use App\Services\Correo\RegistroCorreos;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

#[Group('Correos', weight: 1)]
class CorreoController extends Controller
{
    /**
     * Enviar un correo
     *
     * Guarda el correo y responde `202` de inmediato con el correo en `en_cola`; el envío
     * ocurre en segundo plano. Consulta el resultado con `GET /v1/correos/{id}`.
     *
     * - Al menos un destinatario entre `para`, `cc` y `cco` (máximo 500 entre los tres).
     * - `html` y/o `texto`: al menos uno de los dos.
     * - Con adjuntos usa `multipart/form-data`; sin adjuntos también se acepta JSON.
     * - Todos los correos salen desde la cuenta institucional configurada en el broker.
     */
    #[HeaderParameter('Idempotency-Key', 'Llave única de esta petición (máx. 255). Si se repite con el mismo contenido, devuelve lo creado la primera vez con la cabecera `Idempotent-Replayed: true` y no envía nada nuevo; con otro contenido responde 422. Recomendada siempre.', required: false, type: 'string', example: 'volante-2026-10-4471')]
    public function store(EnviarCorreoRequest $request, RegistroCorreos $registro): JsonResponse
    {
        /** @var Sistema $sistema */
        $sistema = $request->user();
        $datos = $request->validated();
        $adjuntos = $request->file('adjuntos', []);
        $llave = $request->header('Idempotency-Key');
        $huella = $llave ? RegistroCorreos::huella($datos, $adjuntos) : null;

        if ($llave && ($repetido = $this->repetido($sistema, $llave, $huella))) {
            return $repetido;
        }

        $correoId = null;
        try {
            $correo = DB::transaction(function () use ($registro, $sistema, $datos, $adjuntos, $llave, $huella, &$correoId) {
                $correo = $registro->registrar($sistema, $datos, $adjuntos, null, $llave, $huella);
                $correoId = $correo->id;

                return $correo;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Dos peticiones simultáneas con la misma llave: gana la primera.
            $correoId && $registro->eliminarArchivos($correoId);

            return $this->repetido($sistema, $llave, $huella) ?? throw $e;
        } catch (Throwable $e) {
            $correoId && $registro->eliminarArchivos($correoId);
            throw $e;
        }

        EnviarCorreo::dispatch($correo->id);

        /**
         * Correo recibido y encolado.
         *
         * @status 202
         *
         * @body CorreoResource
         */
        return $this->respuesta($correo->fresh(), 202);
    }

    /**
     * Listar correos
     *
     * Correos del sistema que consulta, del más reciente al más antiguo, paginados.
     * Nunca incluye correos de otros sistemas.
     */
    #[QueryParameter('estado', 'Filtra por estado.', type: 'string', example: 'fallido')]
    #[QueryParameter('lote_id', 'Solo los correos de un lote (`lote_id` devuelto por `POST /v1/correos/lote`).')]
    #[QueryParameter('destinatario', 'Correos que tengan esta dirección en para, cc o cco.', example: 'ana@cultura.gob.do')]
    #[QueryParameter('solicitado_por', 'Correos originados por este usuario del sistema.', example: 'rafael.gomez')]
    #[QueryParameter('desde', 'Creados desde esta fecha (ISO 8601).', example: '2026-10-01')]
    #[QueryParameter('hasta', 'Creados hasta esta fecha (ISO 8601).', example: '2026-10-31')]
    #[QueryParameter('por_pagina', 'Resultados por página (1 a 100).', type: 'int', default: 25)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoCorreo::class)],
            'lote_id' => ['nullable', 'string', 'max:26'],
            'destinatario' => ['nullable', 'string', 'max:320'],
            'solicitado_por' => ['nullable', 'string', 'max:255'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var Sistema $sistema */
        $sistema = $request->user();

        $consulta = Correo::query()
            ->where('sistema_id', $sistema->id)
            ->with(['destinatarios', 'adjuntos']);

        foreach (['estado', 'lote_id', 'solicitado_por'] as $campo) {
            if (isset($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (isset($filtros['destinatario'])) {
            $direccion = mb_strtolower($filtros['destinatario']);
            $consulta->whereHas('destinatarios', fn ($d) => $d->where('direccion', $direccion));
        }
        if (isset($filtros['desde'])) {
            $consulta->where('created_at', '>=', $filtros['desde']);
        }
        if (isset($filtros['hasta'])) {
            $consulta->where('created_at', '<=', $filtros['hasta']);
        }

        $correos = $consulta->latest('created_at')->latest('id')->paginate($filtros['por_pagina'] ?? 25);
        // Los enlaces de página conservan los filtros. En sentencia aparte para
        // que Scramble siga reconociendo el paginador al documentar.
        $correos->withQueryString();

        return CorreoResource::collection($correos);
    }

    /**
     * Consultar un correo
     *
     * Estado, destinatarios, adjuntos (solo metadatos) e historial de estados. El cuerpo
     * del correo no se devuelve. Un correo de otro sistema responde `404`.
     *
     * Recuerda: `enviado` significa que Office 365 aceptó el mensaje, no que llegó al buzón.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Sistema $sistema */
        $sistema = $request->user();
        $correo = $sistema->correos()->with(['destinatarios', 'adjuntos', 'eventos'])->findOrFail($id);

        /** @body CorreoResource */
        return $this->respuesta($correo, 200);
    }

    /**
     * Si la llave ya se usó: mismo contenido → el correo original (no se
     * envía otro); contenido distinto → 422.
     */
    private function repetido(Sistema $sistema, string $llave, ?string $huella): ?JsonResponse
    {
        $existente = $sistema->correos()->where('idempotency_key', $llave)->first();
        if (! $existente) {
            return null;
        }

        if ($existente->idempotency_hash !== $huella) {
            return response()->json([
                'mensaje' => 'Esta Idempotency-Key ya se usó con un contenido distinto. Usa una llave nueva para un correo nuevo.',
            ], 422);
        }

        return $this->respuesta($existente, 202)->header('Idempotent-Replayed', 'true');
    }

    private function respuesta(Correo $correo, int $estado): JsonResponse
    {
        $correo->loadMissing(['destinatarios', 'adjuntos']);

        return (new CorreoResource($correo))
            ->response()
            ->setStatusCode($estado)
            ->header('Location', url("/v1/correos/{$correo->id}"));
    }
}
