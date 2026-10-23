<?php

namespace App\Http\Requests\V1;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Límites y comprobaciones de un correo, compartidos por el envío individual
 * y el lote. Las reglas por campo están en EnviarCorreoRequest::rules().
 */
trait ReglasCorreo
{
    /** Office 365 admite hasta 500 destinatarios por mensaje. */
    public const MAX_DESTINATARIOS = 500;

    public const MAX_ADJUNTOS = 20;

    /**
     * Comprobaciones que abarcan varios campos: al menos un destinatario,
     * tope de destinatarios por mensaje y tamaño total de adjuntos.
     */
    protected function validarCorreo(Validator $validator, array $correo, string $p = ''): void
    {
        $total = collect(['para', 'cc', 'cco'])->sum(fn ($t) => count((array) ($correo[$t] ?? [])));

        if ($total === 0) {
            $validator->errors()->add("{$p}para", 'Indica al menos un destinatario en para, cc o cco.');
        } elseif ($total > self::MAX_DESTINATARIOS) {
            $validator->errors()->add("{$p}para", 'Un correo admite como máximo '.self::MAX_DESTINATARIOS.' destinatarios entre para, cc y cco.');
        }

        $maxMb = config('broker.correo.adjunto_max_mb');
        $bytes = collect($correo['adjuntos'] ?? [])
            ->filter(fn ($a) => $a instanceof UploadedFile)
            ->sum(fn (UploadedFile $a) => $a->getSize());

        if ($bytes > $maxMb * 1024 * 1024) {
            $validator->errors()->add("{$p}adjuntos", "Los adjuntos de un correo no pueden sumar más de {$maxMb} MB.");
        }
    }

    /** La cabecera Idempotency-Key, si viene, no puede pasar de 255 caracteres. */
    protected function validarIdempotencyKey(Validator $validator): void
    {
        if (mb_strlen((string) $this->header('Idempotency-Key')) > 255) {
            $validator->errors()->add('Idempotency-Key', 'La cabecera Idempotency-Key no puede tener más de 255 caracteres.');
        }
    }
}
