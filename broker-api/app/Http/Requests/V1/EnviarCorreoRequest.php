<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EnviarCorreoRequest extends FormRequest
{
    use ReglasCorreo;

    /**
     * Reglas de un correo. El lote (EnviarLoteRequest) las reutiliza para cada
     * elemento de «correos». Los comentarios de cada campo salen en la
     * documentación de la API.
     */
    public function rules(): array
    {
        $maxKb = config('broker.correo.adjunto_max_mb') * 1024;

        return [
            /**
             * Destinatarios principales. Al menos un destinatario entre `para`, `cc` y `cco`.
             *
             * @example ["ana@cultura.gob.do"]
             */
            'para' => ['nullable', 'array', 'max:'.self::MAX_DESTINATARIOS],
            'para.*' => ['required', 'email:rfc', 'max:320'],
            /**
             * Con copia: visibles para todos los destinatarios.
             *
             * @example ["jefe@cultura.gob.do"]
             */
            'cc' => ['nullable', 'array', 'max:'.self::MAX_DESTINATARIOS],
            'cc.*' => ['required', 'email:rfc', 'max:320'],
            /**
             * Con copia oculta: nadie ve a estos destinatarios. Se puede enviar solo con `cco`.
             *
             * @example ["rrhh@cultura.gob.do"]
             */
            'cco' => ['nullable', 'array', 'max:'.self::MAX_DESTINATARIOS],
            'cco.*' => ['required', 'email:rfc', 'max:320'],
            /**
             * Asunto del correo.
             *
             * @example Volante de pago — octubre 2026
             */
            'asunto' => ['required', 'string', 'max:500'],
            /**
             * Cuerpo en HTML. Obligatorio si no se envía `texto`.
             *
             * @example <p>Adjunto tu volante de pago.</p>
             */
            'html' => ['nullable', 'string', 'required_without:texto'],
            /**
             * Cuerpo en texto plano. Obligatorio si no se envía `html`; si van los dos, el cliente de correo elige cuál mostrar.
             *
             * @example Adjunto tu volante de pago.
             */
            'texto' => ['nullable', 'string'],
            /**
             * Usuario del sistema que originó el correo, para la trazabilidad. No se envía al destinatario.
             *
             * @example rafael.gomez
             */
            'solicitado_por' => ['nullable', 'string', 'max:255'],
            /**
             * Archivos adjuntos (solo en `multipart/form-data`). Hasta 20 y `BROKER_ADJUNTO_MAX_MB` en total; el destinatario los ve con su nombre original.
             */
            'adjuntos' => ['nullable', 'array', 'max:'.self::MAX_ADJUNTOS],
            'adjuntos.*' => ['file', 'max:'.$maxKb],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $v) => $this->validarCorreo($v, $this->all()),
            fn (Validator $v) => $this->validarIdempotencyKey($v),
        ];
    }
}
