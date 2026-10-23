<?php

namespace App\Console\Commands\Concerns;

use App\Models\Usuario;
use Illuminate\Support\Str;

/**
 * Pide la contraseña de forma oculta (nunca como argumento: quedaría en el
 * historial de la terminal) y busca usuarios por correo.
 */
trait PideClave
{
    private const LARGO_MINIMO = 12;

    protected function pedirClave(): ?string
    {
        $clave = (string) $this->secret('Contraseña (mínimo '.self::LARGO_MINIMO.' caracteres)');

        if (mb_strlen($clave) < self::LARGO_MINIMO) {
            $this->components->error('La contraseña debe tener al menos '.self::LARGO_MINIMO.' caracteres.');

            return null;
        }

        if ($clave !== $this->secret('Repite la contraseña')) {
            $this->components->error('Las contraseñas no coinciden.');

            return null;
        }

        return $clave;
    }

    protected function buscarUsuario(string $correo): ?Usuario
    {
        $usuario = Usuario::where('correo', Str::lower($correo))->first();

        if (! $usuario) {
            $this->components->error("No existe un usuario con el correo «{$correo}». Usa usuarios:listar.");
        }

        return $usuario;
    }
}
