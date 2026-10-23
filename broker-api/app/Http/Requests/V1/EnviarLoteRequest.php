<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EnviarLoteRequest extends FormRequest
{
    use ReglasCorreo;

    /** Correos por petición. Para más, se envían varios lotes. */
    public const MAX_CORREOS = 200;

    public function rules(): array
    {
        // Las mismas reglas que un correo individual, para cada elemento.
        $reglasDeUnCorreo = collect((new EnviarCorreoRequest)->rules())
            ->mapWithKeys(fn ($reglas, $campo) => [
                'correos.*.'.$campo => array_map(
                    fn ($regla) => $regla === 'required_without:texto' ? 'required_without:correos.*.texto' : $regla,
                    $reglas,
                ),
            ])
            ->all();

        return [
            /**
             * Correos individuales (1 a 200), cada uno con los mismos campos que `POST /v1/correos`.
             *
             * @example [{"para": ["empleado1@cultura.gob.do"], "asunto": "Volante de pago — octubre 2026", "html": "<p>Adjunto tu volante.</p>"}]
             */
            'correos' => ['required', 'array', 'min:1', 'max:'.self::MAX_CORREOS],
        ] + $reglasDeUnCorreo;
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                foreach ((array) $this->input('correos', []) as $i => $correo) {
                    $correo = (array) $correo + ['adjuntos' => $this->file("correos.{$i}.adjuntos", [])];
                    $this->validarCorreo($v, $correo, "correos.{$i}.");
                }
            },
            fn (Validator $v) => $this->validarIdempotencyKey($v),
        ];
    }
}
