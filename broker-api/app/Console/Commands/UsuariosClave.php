<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PideClave;
use Illuminate\Console\Command;

class UsuariosClave extends Command
{
    use PideClave;

    protected $signature = 'usuarios:clave {correo : Correo del usuario}';

    protected $description = 'Cambia la contraseña de un usuario de la SPA y cierra sus sesiones';

    public function handle(): int
    {
        $usuario = $this->buscarUsuario($this->argument('correo'));
        if (! $usuario) {
            return self::FAILURE;
        }

        $clave = $this->pedirClave();
        if ($clave === null) {
            return self::FAILURE;
        }

        $usuario->forceFill(['password' => $clave])->save();
        // Quien tuviera la clave anterior pierde también las sesiones abiertas.
        $usuario->sesiones()->whereNull('revocada_at')
            ->update(['revocada_at' => now(), 'motivo_revocacion' => 'cambio_de_clave']);

        $this->components->info("Contraseña de «{$usuario->correo}» cambiada. Sus sesiones abiertas se cerraron.");

        return self::SUCCESS;
    }
}
