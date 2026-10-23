<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;

class UsuariosListar extends Command
{
    protected $signature = 'usuarios:listar';

    protected $description = 'Lista los usuarios de la SPA y sus sesiones abiertas';

    public function handle(): int
    {
        $usuarios = Usuario::withCount(['sesiones as sesiones_abiertas' => fn ($q) => $q
            ->whereNull('revocada_at')
            ->where('expira_at', '>', now())
            ->where('ultimo_uso_at', '>', now()->subMinutes(config('broker.spa.inactividad_minutos'))),
        ])->orderBy('correo')->get();

        if ($usuarios->isEmpty()) {
            $this->components->info('No hay usuarios. Usa usuarios:crear.');

            return self::SUCCESS;
        }

        $this->table(
            ['Correo', 'Nombre', 'Estado', 'Sesiones abiertas', 'Último acceso'],
            $usuarios->map(fn (Usuario $u) => [
                $u->correo,
                $u->nombre,
                $u->estaActivo() ? 'activo' : 'desactivado',
                $u->sesiones_abiertas,
                $u->ultimo_acceso_at ?? 'nunca',
            ]),
        );

        return self::SUCCESS;
    }
}
