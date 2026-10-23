<?php

namespace App\Enums;

/**
 * en_cola → enviando → enviado | fallido
 *
 * «enviado» significa que el servidor SMTP (Office 365) aceptó el mensaje,
 * no que llegó al buzón: los rebotes posteriores no se ven por SMTP.
 */
enum EstadoCorreo: string
{
    case EnCola = 'en_cola';
    case Enviando = 'enviando';
    case Enviado = 'enviado';
    case Fallido = 'fallido';

    public function esFinal(): bool
    {
        return $this === self::Enviado || $this === self::Fallido;
    }
}
