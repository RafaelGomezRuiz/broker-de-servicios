<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $correo_id
 * @property string $tipo  para | cc | cco
 * @property string $direccion
 */
class CorreoDestinatario extends Model
{
    public const TIPOS = ['para', 'cc', 'cco'];

    public $timestamps = false;

    protected $table = 'correo_destinatarios';

    protected $fillable = ['tipo', 'direccion'];
}
