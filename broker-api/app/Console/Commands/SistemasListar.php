<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GestionaSistemas;
use App\Models\Sistema;
use Illuminate\Console\Command;

class SistemasListar extends Command
{
    use GestionaSistemas;

    protected $signature = 'sistemas:listar
        {codigo? : Si se indica, muestra las llaves de ese sistema}';

    protected $description = 'Lista los sistemas registrados, o las llaves de uno';

    public function handle(): int
    {
        $codigo = $this->argument('codigo');

        return $codigo ? $this->listarLlaves($codigo) : $this->listarSistemas();
    }

    private function listarSistemas(): int
    {
        $sistemas = Sistema::withCount('tokens')
            ->withMax('tokens', 'last_used_at')
            ->orderBy('codigo')
            ->get();

        if ($sistemas->isEmpty()) {
            $this->components->info('No hay sistemas registrados. Usa sistemas:crear.');

            return self::SUCCESS;
        }

        $this->table(
            ['Código', 'Nombre', 'Responsable', 'Estado', 'Llaves', 'Último uso'],
            $sistemas->map(fn (Sistema $s) => [
                $s->codigo,
                $s->nombre,
                $s->responsable ?? '—',
                $s->estaActivo() ? 'activo' : 'suspendido',
                $s->tokens_count,
                $s->tokens_max_last_used_at ?? 'nunca',
            ]),
        );

        return self::SUCCESS;
    }

    private function listarLlaves(string $codigo): int
    {
        $sistema = $this->buscarSistema($codigo);
        if (! $sistema) {
            return self::FAILURE;
        }

        $llaves = $sistema->tokens()->orderBy('id')->get();

        $this->components->twoColumnDetail('Sistema', "{$sistema->codigo} — {$sistema->nombre}");
        $this->components->twoColumnDetail('Estado', $sistema->estaActivo() ? 'activo' : 'suspendido');

        if ($llaves->isEmpty()) {
            $this->components->warn('Este sistema no tiene llaves. Usa sistemas:nueva-llave.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nombre', 'Creada', 'Último uso'],
            $llaves->map(fn ($llave) => [
                $llave->id,
                $llave->name,
                $llave->created_at,
                $llave->last_used_at ?? 'nunca',
            ]),
        );

        return self::SUCCESS;
    }
}
