<?php

namespace App\Models;

use Database\Factories\SistemaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

/**
 * Un sistema consumidor del broker. Se autentica con sus llaves (Sanctum).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $responsable
 * @property \Illuminate\Support\Carbon|null $suspendido_at
 */
class Sistema extends Model
{
    /** @use HasFactory<SistemaFactory> */
    use HasApiTokens, HasFactory;

    protected $table = 'sistemas';

    protected $fillable = ['codigo', 'nombre', 'responsable'];

    protected function casts(): array
    {
        return [
            'suspendido_at' => 'datetime',
        ];
    }

    public function correos(): HasMany
    {
        return $this->hasMany(Correo::class);
    }

    public function estaActivo(): bool
    {
        return $this->suspendido_at === null;
    }

    /**
     * Genera una llave nueva. El texto plano solo existe en el valor
     * devuelto: en la base de datos queda únicamente su hash.
     */
    public function generarLlave(string $nombre = 'principal'): NewAccessToken
    {
        // Sin permisos por sistema en la v1: toda llave puede usar /v1.
        return $this->createToken($nombre, ['*']);
    }
}
