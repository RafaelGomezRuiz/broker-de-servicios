<?php

namespace App\Jobs;

use App\Enums\EstadoCorreo;
use App\Models\Correo;
use App\Models\CorreoAdjunto;
use App\Models\CorreoVerificacion;
use App\Services\Correo\TraductorErroresSmtp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Entrega un correo al servidor SMTP.
 *
 * - Errores técnicos (conexión, inicio de sesión, 4xx): se reintenta con
 *   espera creciente hasta MAX_INTENTOS.
 * - Rechazos permanentes del servidor (5xx al enviar: destinatario inválido,
 *   Send As denegado, mensaje muy grande): fallido sin reintentar.
 *
 * Los logs solo llevan IDs: nunca cuerpos, destinatarios ni adjuntos.
 */
class EnviarCorreo implements ShouldQueue
{
    use Queueable;

    public const MAX_INTENTOS = 5;

    /** Espera antes de cada reintento, en segundos: 1, 5, 15 y 30 minutos. */
    public const ESPERAS = [60, 300, 900, 1800];

    /** Menor que retry_after (90 s) de la cola database, para que un job lento no se duplique. */
    public int $timeout = 60;

    /** Excepciones no controladas toleradas antes de dar el job por perdido. */
    public int $maxExceptions = 3;

    public function __construct(public string $correoId)
    {
        $this->onQueue('correos');
    }

    /**
     * El límite por minuto devuelve el job a la cola sin contar como intento
     * del correo (los intentos se cuentan en la tabla, no en la cola).
     */
    public function middleware(): array
    {
        return [new RateLimited('envio-correo')];
    }

    /** Con retryUntil, la cola no descarta el job por número de intentos. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(TraductorErroresSmtp $traductor): void
    {
        $correo = Correo::with(['destinatarios', 'adjuntos'])->find($this->correoId);

        // Ya resuelto (job duplicado o reintento tardío): nada que hacer.
        if (! $correo || $correo->estado->esFinal()) {
            return;
        }

        if ($correo->adjuntos->contains(fn (CorreoAdjunto $a) => ! $a->ruta || ! Storage::disk('local')->exists($a->ruta))) {
            $correo->registrarEstado(EstadoCorreo::Fallido, 'Un adjunto ya no está disponible en el broker.', [
                'error_mensaje' => 'Un adjunto ya no está disponible en el broker.',
            ]);

            return;
        }

        $intento = $correo->intentos + 1;
        $correo->registrarEstado(EstadoCorreo::Enviando, "Intento {$intento}", ['intentos' => $intento]);

        try {
            $enviado = Mail::send([], [], fn (Message $m) => $this->armar($m, $correo));
        } catch (TransportExceptionInterface $e) {
            $this->manejarFallo($correo, $e, $traductor);

            return;
        }

        $correo->registrarEstado(EstadoCorreo::Enviado, 'Aceptado por el servidor de correo', [
            'enviado_at' => now(),
            'message_id' => $enviado?->getMessageId(),
            'error_mensaje' => null,
            'error_detalle' => null,
        ]);

        // Enviado: los adjuntos ya no hacen falta en el broker.
        $correo->adjuntos->each->eliminarArchivo();

        Log::info('Correo enviado', ['correo_id' => $correo->id, 'sistema_id' => $correo->sistema_id, 'intento' => $intento]);
    }

    /** Si el job muere por algo inesperado (timeout, error de código), el correo no queda colgado. */
    public function failed(Throwable $e): void
    {
        $correo = Correo::find($this->correoId);
        if ($correo && ! $correo->estado->esFinal()) {
            $correo->registrarEstado(EstadoCorreo::Fallido, 'Error interno del broker al enviar.', [
                'error_mensaje' => 'Error interno del broker al enviar.',
                'error_detalle' => mb_substr($e->getMessage(), 0, 2000),
            ]);
        }
    }

    private function armar(Message $m, Correo $correo): void
    {
        if ($para = $correo->direcciones('para')) {
            $m->to($para);
        }
        if ($cc = $correo->direcciones('cc')) {
            $m->cc($cc);
        }
        if ($cco = $correo->direcciones('cco')) {
            $m->bcc($cco);
        }

        $m->subject($correo->asunto);

        if ($correo->cuerpo_html !== null) {
            $m->html($correo->cuerpo_html);
        }
        if ($correo->cuerpo_texto !== null) {
            $m->text($correo->cuerpo_texto);
        }

        foreach ($correo->adjuntos as $adjunto) {
            $m->attach(Storage::disk('local')->path($adjunto->ruta), [
                'as' => $adjunto->nombre,
                'mime' => $adjunto->mime,
            ]);
        }
    }

    private function manejarFallo(Correo $correo, TransportExceptionInterface $e, TraductorErroresSmtp $traductor): void
    {
        $smtp = config('mail.mailers.smtp');
        $error = $traductor->traducir($e, $smtp['host'].':'.$smtp['port'], CorreoVerificacion::ETAPA_ENVIO);

        $cambios = [
            'error_mensaje' => $error['mensaje'],
            'error_detalle' => mb_substr($e->getMessage(), 0, 2000),
        ];

        // Un 5xx durante el envío es un rechazo definitivo; uno de conexión o
        // de inicio de sesión es de configuración y vale la pena reintentar.
        $permanente = $error['etapa'] === CorreoVerificacion::ETAPA_ENVIO
            && $error['codigo'] !== null && $error['codigo'] >= 500;

        Log::warning('Correo no enviado', [
            'correo_id' => $correo->id,
            'intento' => $correo->intentos,
            'codigo_smtp' => $error['codigo'],
            'permanente' => $permanente,
        ]);

        if ($permanente || $correo->intentos >= self::MAX_INTENTOS) {
            $correo->registrarEstado(EstadoCorreo::Fallido, $error['mensaje'], $cambios);

            return;
        }

        $espera = self::ESPERAS[$correo->intentos - 1] ?? self::ESPERAS[array_key_last(self::ESPERAS)];
        $correo->registrarEstado(EstadoCorreo::EnCola, "Reintento en {$espera} s: {$error['mensaje']}", $cambios);
        $this->release($espera);
    }
}
