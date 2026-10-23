<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PideClave;
use Illuminate\Console\Command;

class UsuariosDesactivar extends Command
{
    use PideClave;

    protected $signature = 'usuarios:desactivar
        {correo : Correo del usuario}
        {--reactivar : Vuelve a permitir la entrada}';

    protected $description = 'Desactiva un usuario de la SPA y cierra todas sus sesiones, o lo reactiva';

    public function handle(): int
    {
        $usuario = $this->buscarUsuario($this->argument('correo'));
        if (! $usuario) {
            return self::FAILURE;
        }

        if ($this->option('reactivar')) {
            $usuario->forceFill(['desactivado_at' => null])->save();
            $this->components->info("«{$usuario->correo}» puede volver a entrar.");

            return self::SUCCESS;
        }

        $usuario->forceFill(['desactivado_at' => now()])->save();
        $cerradas = $usuario->sesiones()->whereNull('revocada_at')
            ->update(['revocada_at' => now(), 'motivo_revocacion' => 'usuario_desactivado']);

        $this->components->info("«{$usuario->correo}» desactivado. Sesiones cerradas: {$cerradas}.");

        return self::SUCCESS;
    }
}
