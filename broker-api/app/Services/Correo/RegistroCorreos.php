<?php

namespace App\Services\Correo;

use App\Enums\EstadoCorreo;
use App\Models\Correo;
use App\Models\CorreoLote;
use App\Models\Sistema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda un correo recibido por la API: datos, destinatarios y adjuntos.
 * No envía nada; el envío lo hace el job EnviarCorreo.
 */
class RegistroCorreos
{
    /**
     * Debe llamarse dentro de una transacción. Si falla, quien llama borra
     * los archivos con eliminarArchivos().
     *
     * @param  array<string, mixed>  $datos  Datos validados de un correo.
     * @param  list<UploadedFile>  $adjuntos
     */
    public function registrar(
        Sistema $sistema,
        array $datos,
        array $adjuntos,
        ?CorreoLote $lote = null,
        ?string $idempotencyKey = null,
        ?string $idempotencyHash = null,
    ): Correo {
        /** @var Correo $correo */
        $correo = $sistema->correos()->create([
            'lote_id' => $lote?->id,
            'idempotency_key' => $idempotencyKey,
            'idempotency_hash' => $idempotencyHash,
            'asunto' => $datos['asunto'],
            'cuerpo_html' => $datos['html'] ?? null,
            'cuerpo_texto' => $datos['texto'] ?? null,
            'solicitado_por' => $datos['solicitado_por'] ?? null,
            'estado' => EstadoCorreo::EnCola,
        ]);

        foreach (['para', 'cc', 'cco'] as $tipo) {
            foreach (array_unique(array_map('mb_strtolower', $datos[$tipo] ?? [])) as $direccion) {
                $correo->destinatarios()->create(['tipo' => $tipo, 'direccion' => $direccion]);
            }
        }

        foreach (array_values($adjuntos) as $i => $archivo) {
            // El destinatario ve el nombre original; en disco se usa uno seguro.
            $nombre = $this->nombreVisible($archivo->getClientOriginalName());
            $ruta = $archivo->storeAs("adjuntos/{$correo->id}", ($i + 1).'-'.$this->nombreEnDisco($nombre), 'local');

            $correo->adjuntos()->create([
                'nombre' => $nombre,
                'mime' => $archivo->getMimeType() ?: 'application/octet-stream',
                'tamano_bytes' => $archivo->getSize(),
                'sha256' => hash_file('sha256', $archivo->getRealPath()),
                'ruta' => $ruta,
            ]);
        }

        $correo->eventos()->create(['estado' => EstadoCorreo::EnCola->value, 'detalle' => 'Recibido']);

        return $correo;
    }

    /** Borra los archivos de un correo cuyo registro no llegó a guardarse. */
    public function eliminarArchivos(string $correoId): void
    {
        Storage::disk('local')->deleteDirectory("adjuntos/{$correoId}");
    }

    /**
     * Huella del contenido para la idempotencia: la misma Idempotency-Key
     * con un contenido distinto se rechaza en vez de devolver otro correo.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<UploadedFile>  $adjuntos
     */
    public static function huella(array $datos, array $adjuntos): string
    {
        unset($datos['adjuntos']);
        ksort($datos);

        $archivos = array_map(fn (UploadedFile $a) => hash_file('sha256', $a->getRealPath()), $adjuntos);

        return hash('sha256', json_encode([$datos, $archivos]));
    }

    /** Nombre original sin rutas ni caracteres de control. */
    private function nombreVisible(string $original): string
    {
        $nombre = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $original))));

        return mb_substr($nombre, 0, 200) ?: 'adjunto';
    }

    /** Nombre apto para el sistema de archivos, conservando la extensión. */
    private function nombreEnDisco(string $nombre): string
    {
        $extension = pathinfo($nombre, PATHINFO_EXTENSION);
        $base = Str::slug(pathinfo($nombre, PATHINFO_FILENAME)) ?: 'adjunto';

        return Str::limit($base, 150, '').($extension ? '.'.Str::lower(Str::limit($extension, 10, '')) : '');
    }
}
