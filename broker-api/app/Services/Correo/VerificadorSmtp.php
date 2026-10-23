<?php

namespace App\Services\Correo;

use App\Models\CorreoVerificacion;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * Comprueba que la cuenta de correo configurada (MAIL_*) funciona:
 * conecta, negocia TLS e inicia sesión. Opcionalmente envía un correo real.
 *
 * Cada verificación queda guardada en correo_verificaciones.
 */
class VerificadorSmtp
{
    public function __construct(private TraductorErroresSmtp $traductor) {}

    public function verificar(?string $enviarPruebaA = null, string $origen = 'manual'): CorreoVerificacion
    {
        $config = config('mail.mailers.smtp');
        $servidor = $config['host'].':'.$config['port'];
        $usuario = $config['username'] ?: null;
        $inicio = hrtime(true);

        $registro = [
            'servidor' => $servidor,
            'usuario' => $usuario,
            'prueba_enviada_a' => $enviarPruebaA,
            'origen' => $origen,
        ];

        // Siempre un transporte nuevo: el que Laravel guarda en memoria puede
        // venir de un intento fallido anterior.
        Mail::purge('smtp');
        $etapa = CorreoVerificacion::ETAPA_CONEXION;

        try {
            $transporte = Mail::mailer('smtp')->getSymfonyTransport();
            if (! $transporte instanceof SmtpTransport) {
                throw new \LogicException('El mailer «smtp» no usa un transporte SMTP.');
            }

            // start() conecta, hace EHLO, STARTTLS y, si hay usuario, AUTH.
            $transporte->start();
            $transporte->stop();

            if ($enviarPruebaA) {
                $etapa = CorreoVerificacion::ETAPA_ENVIO;
                Mail::mailer('smtp')->raw(
                    "Correo de prueba del Broker de Servicios.\n\n"
                    ."Si lo recibiste, la cuenta {$usuario} puede enviar desde {$servidor}.\n"
                    .'Enviado: '.now()->toDateTimeString(),
                    fn ($mensaje) => $mensaje->to($enviarPruebaA)->subject('Broker de Servicios: correo de prueba'),
                );
            }

            $comprobado = $usuario ? 'Conexión e inicio de sesión correctos' : 'Conexión correcta (sin autenticación)';

            return CorreoVerificacion::create($registro + [
                'exito' => true,
                'etapa' => CorreoVerificacion::ETAPA_COMPLETA,
                'codigo_smtp' => null,
                'mensaje' => $enviarPruebaA
                    ? "{$comprobado}; correo de prueba enviado a {$enviarPruebaA}."
                    : "{$comprobado}.",
                'detalle' => null,
                'duracion_ms' => $this->milisegundosDesde($inicio),
            ]);
        } catch (Throwable $e) {
            $traducido = $this->traductor->traducir($e, $servidor, $etapa);

            return CorreoVerificacion::create($registro + [
                'exito' => false,
                'etapa' => $traducido['etapa'],
                'codigo_smtp' => $traducido['codigo'],
                'mensaje' => $traducido['mensaje'],
                'detalle' => Str::limit($e->getMessage(), 2000),
                'duracion_ms' => $this->milisegundosDesde($inicio),
            ]);
        } finally {
            Mail::purge('smtp');
        }
    }

    private function milisegundosDesde(int $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1e6);
    }
}
