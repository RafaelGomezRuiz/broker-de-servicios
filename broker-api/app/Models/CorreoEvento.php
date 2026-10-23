<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un cambio de estado de un correo. Solo se agregan, nunca se editan.
 *
 * @property int $id
 * @property string $correo_id
 * @property string $estado
 * @property string|null $detalle
 * @property \Illuminate\Support\Carbon $created_at
 */
class CorreoEvento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'correo_eventos';

    protected $fillable = ['estado', 'detalle'];
}
