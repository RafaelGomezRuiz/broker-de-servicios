<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GestionaSistemas;
use Illuminate\Console\Command;

class SistemasSuspender extends Command
{
    use GestionaSistemas;

    protected $signature = 'sistemas:suspender
        {codigo : Código del sistema}
        {--reactivar : Vuelve a activar un sistema suspendido}';

    protected $description = 'Suspende un sistema (todas sus llaves dejan de funcionar) o lo reactiva';

    public function handle(): int
    {
        $sistema = $this->buscarSistema($this->argument('codigo'));
        if (! $sistema) {
            return self::FAILURE;
        }

        if ($this->option('reactivar')) {
            $sistema->forceFill(['suspendido_at' => null])->save();
            $this->components->info("«{$sistema->codigo}» está activo; sus llaves vuelven a funcionar.");

            return self::SUCCESS;
        }

        if (! $sistema->estaActivo()) {
            $this->components->info("«{$sistema->codigo}» ya estaba suspendido desde {$sistema->suspendido_at}.");

            return self::SUCCESS;
        }

        $sistema->forceFill(['suspendido_at' => now()])->save();
        $this->components->info("«{$sistema->codigo}» suspendido. Sus llaves se conservan pero no funcionan.");
        $this->line('  Para reactivarlo: php artisan sistemas:suspender '.$sistema->codigo.' --reactivar');

        return self::SUCCESS;
    }
}
