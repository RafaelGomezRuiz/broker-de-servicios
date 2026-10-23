<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Persona que entra a la SPA del broker (solo lectura en la v1).
 * Se crean con `php artisan usuarios:crear`.
 *
 * @property int $id
 * @property string $nombre
 * @property string $correo
 * @property string $password
 * @property \Illuminate\Support\Carbon|null $desactivado_at
 * @property \Illuminate\Support\Carbon|null $ultimo_acceso_at
 */
class Usuario extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory;

    protected $table = 'usuarios';

    protected $fillable = ['nombre', 'correo', 'password'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'desactivado_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
        ];
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class);
    }

    public function estaActivo(): bool
    {
        return $this->desactivado_at === null;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
