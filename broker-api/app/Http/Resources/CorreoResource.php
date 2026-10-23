<?php

namespace App\Http\Resources;

use App\Models\Correo;
use App\Models\CorreoAdjunto;
use App\Models\CorreoEvento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lo que un sistema ve de sus correos. No incluye los cuerpos (pueden
 * contener datos sensibles) ni el detalle técnico del servidor.
 *
 * Los comentarios de cada campo salen en la documentación de la API.
 *
 * @mixin Correo
 */
class CorreoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /**
             * Identificador del correo (ULID).
             *
             * @example 01K86XK2Q4Z7N3P5R8T0V2W4Y6
             */
            'id' => $this->id,
            /** Lote al que pertenece, si se envió con `POST /v1/correos/lote`. */
            'lote_id' => $this->lote_id,
            /**
             * `en_cola` → `enviando` → `enviado` | `fallido`. **`enviado` significa que Office 365 aceptó el mensaje, no que llegó al buzón.**
             *
             * @var 'en_cola'|'enviando'|'enviado'|'fallido'
             */
            'estado' => $this->estado->value,
            /** Asunto del correo. */
            'asunto' => $this->asunto,
            /** Destinatarios principales. */
            'para' => $this->direcciones('para'),
            /** Con copia. */
            'cc' => $this->direcciones('cc'),
            /** Con copia oculta. */
            'cco' => $this->direcciones('cco'),
            /**
             * Metadatos de los adjuntos. Los archivos se borran del broker al enviarse.
             *
             * @var list<array{nombre: string, mime: string, tamano_bytes: int}>
             */
            'adjuntos' => $this->adjuntos->map(fn (CorreoAdjunto $a) => [
                'nombre' => $a->nombre,
                'mime' => $a->mime,
                'tamano_bytes' => $a->tamano_bytes,
            ])->values(),
            /** Usuario del sistema que originó el correo. */
            'solicitado_por' => $this->solicitado_por,
            /** Intentos de entrega realizados (máximo 5). */
            'intentos' => $this->intentos,
            /** Último error, en español, si lo hubo. */
            'error' => $this->error_mensaje,
            /** Message-ID asignado al enviarse; sirve para rastrear el correo en Office 365. */
            'message_id' => $this->message_id,
            /** Fecha de recepción (ISO 8601). */
            'creado_at' => $this->created_at?->toIso8601String(),
            /** Fecha en que Office 365 aceptó el mensaje (ISO 8601). */
            'enviado_at' => $this->enviado_at?->toIso8601String(),
            /**
             * Historial de estados. Solo en `GET /v1/correos/{id}`.
             *
             * @var list<array{estado: string, detalle: string|null, fecha: string|null}>
             */
            'eventos' => $this->whenLoaded('eventos', fn () => $this->eventos->map(fn (CorreoEvento $e) => [
                'estado' => $e->estado,
                'detalle' => $e->detalle,
                'fecha' => $e->created_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
