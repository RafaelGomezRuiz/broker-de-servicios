<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GestionaSistemas;
use Illuminate\Console\Command;

class SistemasNuevaLlave extends Command
{
    use GestionaSistemas;

    protected $signature = 'sistemas:nueva-llave
        {codigo : Código del sistema}
        {--nombre= : Etiqueta de la llave (por defecto, rotacion-AAAA-MM-DD)}';

    protected $description = 'Genera una llave adicional para un sistema (rotación o llave perdida)';

    public function handle(): int
    {
        $sistema = $this->buscarSistema($this->argument('codigo'));
        if (! $sistema) {
            return self::FAILURE;
        }

        $nombre = $this->option('nombre') ?: 'rotacion-'.now()->format('Y-m-d');
        $llave = $sistema->generarLlave($nombre);

        $this->components->info("Llave «{$nombre}» creada para «{$sistema->codigo}».");
        $this->mostrarLlave($sistema, $llave);
        $this->line('  Las llaves anteriores siguen activas: revócalas con sistemas:revocar-llave');
        $this->line('  cuando el sistema ya use la nueva.');
        $this->newLine();

        return self::SUCCESS;
    }
}
