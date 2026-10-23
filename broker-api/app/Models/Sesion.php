<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesión de un usuario en la SPA. Su refresh token vive en una cookie
 * httpOnly; aquí solo se guarda el hash.
 *
 * Una sesión deja de valer si: se revocó (logout, reutilización de un token
 * viejo, usuario desactivado), pasó su vida máxima (JWT_REFRESH_TTL) o lleva
 * más de JWT_INACTIVIDAD_TTL minutos sin uso.
 *
 * @property int $id
 * @property int $usuario_id
 * @property string $token_hash
 * @property string|null $token_anterior_hash
 * @property \Illuminate\Support\Carbon|null $rotada_at
 * @property \Illuminate\Support\Carbon $ultimo_uso_at
 * @property \Illuminate\Support\Carbon $expira_at
 * @property \Illuminate\Support\Carbon|null $revocada_at
 * @property string|null $motivo_revocacion
 */
class Sesion extends Model
{
    use MassPrunable;

    protected $table = 'sesiones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rotada_at' => 'datetime',
            'ultimo_uso_at' => 'datetime',
            'expira_at' => 'datetime',
            'revocada_at' => 'datetime',
        ];
    }

    /** model:prune borra las sesiones vencidas hace más de 30 días. */
    public function prunable(): Builder
    {
        return static::where('expira_at', '<', now()->subDays(30));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function estaVigente(): bool
    {
        $inactividad = (int) config('broker.spa.inactividad_minutos');

        return $this->revocada_at === null
            && $this->expira_at->isFuture()
            && $this->ultimo_uso_at->greaterThan(now()->subMinutes($inactividad))
            && $this->usuario?->estaActivo();
    }

    public function revocar(string $motivo): void
    {
        if ($this->revocada_at === null) {
            $this->forceFill(['revocada_at' => now(), 'motivo_revocacion' => $motivo])->save();
        }
    }
}
