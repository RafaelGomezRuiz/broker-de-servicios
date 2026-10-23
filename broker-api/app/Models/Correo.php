<?php

namespace App\Models;

use App\Enums\EstadoCorreo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $sistema_id
 * @property string|null $lote_id
 * @property string|null $idempotency_key
 * @property string|null $idempotency_hash
 * @property string $asunto
 * @property string|null $cuerpo_html
 * @property string|null $cuerpo_texto
 * @property string|null $solicitado_por
 * @property EstadoCorreo $estado
 * @property int $intentos
 * @property string|null $error_mensaje
 * @property string|null $error_detalle
 * @property string|null $message_id
 * @property \Illuminate\Support\Carbon|null $enviado_at
 * @property \Illuminate\Support\Carbon|null $contenido_purgado_at
 */
class Correo extends Model
{
    use HasUlids;

    protected $table = 'correos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCorreo::class,
            'intentos' => 'integer',
            'enviado_at' => 'datetime',
            'contenido_purgado_at' => 'datetime',
        ];
    }

    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(CorreoDestinatario::class);
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(CorreoAdjunto::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(CorreoEvento::class)->orderBy('id');
    }

    /** Cambia el estado y deja constancia en el historial. */
    public function registrarEstado(EstadoCorreo $estado, ?string $detalle = null, array $cambios = []): void
    {
        $this->forceFill(['estado' => $estado] + $cambios)->save();
        $this->eventos()->create([
            'estado' => $estado->value,
            'detalle' => $detalle ? mb_substr($detalle, 0, 500) : null,
        ]);
    }

    /** @return list<string> */
    public function direcciones(string $tipo): array
    {
        return $this->destinatarios->where('tipo', $tipo)->pluck('direccion')->values()->all();
    }
}
