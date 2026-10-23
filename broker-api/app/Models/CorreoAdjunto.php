<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Metadatos de un adjunto. El archivo vive en el disco privado solo hasta
 * que el correo se envía (o hasta la purga por retención si falla).
 *
 * @property int $id
 * @property string $correo_id
 * @property string $nombre
 * @property string $mime
 * @property int $tamano_bytes
 * @property string $sha256
 * @property string|null $ruta
 * @property \Illuminate\Support\Carbon|null $eliminado_at
 */
class CorreoAdjunto extends Model
{
    protected $table = 'correo_adjuntos';

    protected $fillable = ['nombre', 'mime', 'tamano_bytes', 'sha256', 'ruta'];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'eliminado_at' => 'datetime',
        ];
    }

    public function eliminarArchivo(): void
    {
        if ($this->ruta) {
            Storage::disk('local')->delete($this->ruta);
        }

        $this->forceFill(['ruta' => null, 'eliminado_at' => now()])->save();
    }
}
