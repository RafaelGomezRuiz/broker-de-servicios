<?php

namespace App\Console\Commands\Concerns;

use App\Models\Sistema;
use Laravel\Sanctum\NewAccessToken;

/**
 * Utilidades compartidas por los comandos sistemas:*.
 */
trait GestionaSistemas
{
    /** Busca el sistema por código o termina el comando con un error claro. */
    protected function buscarSistema(string $codigo): ?Sistema
    {
        $sistema = Sistema::where('codigo', $codigo)->first();

        if (! $sistema) {
            $this->components->error("No existe un sistema con el código «{$codigo}». Usa sistemas:listar.");
        }

        return $sistema;
    }

    /**
     * Muestra la llave recién creada. Es la ÚNICA vez que se puede ver:
     * en la base de datos solo queda su hash.
     */
    protected function mostrarLlave(Sistema $sistema, NewAccessToken $llave): void
    {
        $this->newLine();
        $this->components->warn('Copia esta llave ahora: no se volverá a mostrar.');
        $this->line("  Sistema: {$sistema->codigo}");
        $this->line("  Llave:   <fg=green;options=bold>{$llave->plainTextToken}</>");
        $this->newLine();
        $this->line('  Guárdala en el Secret del sistema como BROKER_API_KEY.');
        $this->newLine();
    }
}
