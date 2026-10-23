<?php

namespace App\Console\Commands;

use App\Models\Correo;
use App\Models\CorreoAdjunto;
use Illuminate\Console\Command;

/**
 * Retención: pasados BROKER_RETENCION_DIAS se borran los cuerpos y los
 * adjuntos de los correos (los volantes contienen salarios). Se conservan
 * los metadatos para la trazabilidad: sistema, destinatarios, asunto,
 * estado e historial.
 */
class CorreosPurgar extends Command
{
    protected $signature = 'correos:purgar';

    protected $description = 'Borra cuerpos y adjuntos de los correos más antiguos que la retención';

    public function handle(): int
    {
        $dias = config('broker.correo.retencion_dias');
        $limite = now()->subDays($dias);
        $total = 0;

        Correo::query()
            ->whereNull('contenido_purgado_at')
            ->where('created_at', '<', $limite)
            ->with('adjuntos')
            ->chunkById(200, function ($correos) use (&$total) {
                foreach ($correos as $correo) {
                    $correo->adjuntos
                        ->filter(fn (CorreoAdjunto $a) => $a->eliminado_at === null)
                        ->each->eliminarArchivo();

                    $correo->forceFill([
                        'cuerpo_html' => null,
                        'cuerpo_texto' => null,
                        'contenido_purgado_at' => now(),
                    ])->save();

                    $total++;
                }
            });

        $this->components->info("Correos purgados (más de {$dias} días): {$total}.");

        return self::SUCCESS;
    }
}
