<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Resultado de una verificación de la cuenta de correo.
 *
 * @property int $id
 * @property bool $exito
 * @property string $etapa
 * @property int|null $codigo_smtp
 * @property string $mensaje
 * @property string|null $detalle
 * @property string $servidor
 * @property string|null $usuario
 * @property string|null $prueba_enviada_a
 * @property int $duracion_ms
 * @property string $origen
 * @property \Illuminate\Support\Carbon $created_at
 */
class CorreoVerificacion extends Model
{
    public const ETAPA_CONEXION = 'conexion';

    public const ETAPA_AUTENTICACION = 'autenticacion';

    public const ETAPA_ENVIO = 'envio';

    public const ETAPA_COMPLETA = 'completa';

    protected $table = 'correo_verificaciones';

    protected $fillable = [
        'exito', 'etapa', 'codigo_smtp', 'mensaje', 'detalle', 'servidor',
        'usuario', 'prueba_enviada_a', 'duracion_ms', 'origen',
    ];

    protected function casts(): array
    {
        return [
            'exito' => 'boolean',
            'codigo_smtp' => 'integer',
            'duracion_ms' => 'integer',
        ];
    }

    /** La verificación más reciente: el estado actual de la cuenta. */
    public static function ultima(): ?self
    {
        return static::latest('id')->first();
    }
}
