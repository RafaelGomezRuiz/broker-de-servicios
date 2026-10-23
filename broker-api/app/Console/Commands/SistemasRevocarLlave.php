<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GestionaSistemas;
use Illuminate\Console\Command;

class SistemasRevocarLlave extends Command
{
    use GestionaSistemas;

    protected $signature = 'sistemas:revocar-llave
        {codigo : Código del sistema}
        {llave : ID de la llave (ver sistemas:listar <codigo>)}
        {--force : No pedir confirmación}';

    protected $description = 'Revoca una llave de un sistema; deja de funcionar al instante';

    public function handle(): int
    {
        $sistema = $this->buscarSistema($this->argument('codigo'));
        if (! $sistema) {
            return self::FAILURE;
        }

        // Se busca dentro del sistema: un ID de otro sistema no se puede revocar por error.
        $llave = $sistema->tokens()->whereKey($this->argument('llave'))->first();
        if (! $llave) {
            $this->components->error("El sistema «{$sistema->codigo}» no tiene una llave con ID {$this->argument('llave')}.");

            return self::FAILURE;
        }

        $restantes = $sistema->tokens()->count() - 1;
        if ($restantes === 0) {
            $this->components->warn("Es la única llave de «{$sistema->codigo}»: el sistema dejará de poder usar el broker.");
        }

        if (! $this->option('force') && ! $this->confirm("¿Revocar la llave «{$llave->name}» (ID {$llave->id})?")) {
            $this->components->info('No se revocó nada.');

            return self::SUCCESS;
        }

        $llave->delete();
        $this->components->info("Llave «{$llave->name}» revocada.");

        return self::SUCCESS;
    }
}
