<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un envío masivo: varios correos individuales creados en una sola petición.
 *
 * @property string $id
 * @property int $sistema_id
 * @property string|null $idempotency_key
 * @property string|null $idempotency_hash
 * @property int $total
 */
class CorreoLote extends Model
{
    use HasUlids;

    protected $table = 'correo_lotes';

    protected $guarded = ['id'];

    public function correos(): HasMany
    {
        return $this->hasMany(Correo::class, 'lote_id')->orderBy('id');
    }
}
