<?php

namespace App\Documentacion;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Ajusta el documento que genera Scramble a lo que la API responde de verdad.
 *
 * - Errores: Scramble usa el formato de Laravel ({message, errors}); el broker
 *   responde {mensaje, errores} en español (ver bootstrap/app.php).
 * - 429: todas las rutas /v1 tienen límite por sistema.
 * - 404: un correo inexistente o de otro sistema (findOrFail sobre la relación,
 *   que Scramble no detecta).
 * - 200 sobrante: en los envíos (202), el camino de idempotencia deja una
 *   respuesta 200 genérica que no existe.
 */
class AjustesOpenApi
{
    private const DESCRIPCIONES = [
        401 => 'Llave ausente, no válida o revocada, o sistema suspendido.',
        403 => 'Acción no permitida.',
        404 => 'No encontrado (o pertenece a otro sistema).',
        413 => 'La petición supera el tamaño máximo. Reduce los adjuntos o divide el lote.',
        422 => 'Los datos enviados no son válidos.',
        429 => 'Demasiadas peticiones del sistema. Espera los segundos de la cabecera Retry-After.',
    ];

    /** Respuestas que Scramble no infiere, por operationId (nombre de la ruta). */
    private const RESPUESTAS_EXTRA = [
        'correos.consultar' => [404],
        'correos.enviar' => [413],
        'correos.enviarLote' => [413],
    ];

    public function __invoke(OpenApi $openApi): void
    {
        foreach ($openApi->components->responses as $respuesta) {
            if ($respuesta instanceof Response && is_int($respuesta->code)) {
                $this->comoError($respuesta);
            }
        }

        foreach ($openApi->paths as $ruta) {
            foreach ($ruta->operations as $operacion) {
                $this->ajustarOperacion($operacion);
            }
        }
    }

    private function ajustarOperacion(Operation $operacion): void
    {
        $codigos = array_map(fn ($r) => $this->codigo($r), $operacion->responses ?? []);

        if (in_array(202, $codigos, true)) {
            $operacion->responses = array_values(array_filter(
                $operacion->responses,
                fn ($r) => $this->codigo($r) !== 200,
            ));
        }

        foreach ([...(self::RESPUESTAS_EXTRA[$operacion->operationId] ?? []), 429] as $codigo) {
            $operacion->addResponse($this->comoError(Response::make($codigo)));
        }
    }

    private function codigo(Response|Reference $respuesta): int|string|null
    {
        return $respuesta instanceof Reference ? $respuesta->resolve()->code : $respuesta->code;
    }

    private function comoError(Response $respuesta): Response
    {
        $cuerpo = (new ObjectType)
            ->addProperty('mensaje', (new StringType)->setDescription('Explicación del error, en español.'))
            ->setRequired(['mensaje']);

        if ($respuesta->code === 422) {
            $cuerpo
                ->addProperty('errores', (new ObjectType)
                    ->setDescription('Problemas por campo. La clave es el campo (p. ej. «para.0», «correos.3.asunto»).')
                    ->additionalProperties((new ArrayType)->setItems(new StringType)))
                ->setRequired(['mensaje', 'errores']);
        }

        return $respuesta
            ->setDescription(self::DESCRIPCIONES[$respuesta->code] ?? 'Error.')
            ->setContent('application/json', Schema::fromType($cuerpo));
    }
}
