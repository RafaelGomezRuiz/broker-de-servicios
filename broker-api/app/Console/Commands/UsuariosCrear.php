<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PideClave;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UsuariosCrear extends Command
{
    use PideClave;

    protected $signature = 'usuarios:crear
        {correo : Correo con el que entrará a la SPA}
        {--nombre= : Nombre completo}';

    protected $description = 'Crea un usuario de la SPA (la contraseña se pide de forma oculta)';

    public function handle(): int
    {
        $datos = [
            'correo' => Str::lower($this->argument('correo')),
            'nombre' => $this->option('nombre') ?? $this->ask('Nombre completo'),
        ];

        $validador = Validator::make($datos, [
            'correo' => ['required', 'email', 'unique:usuarios,correo'],
            'nombre' => ['required', 'string', 'max:255'],
        ], [
            'correo.unique' => 'Ya existe un usuario con ese correo.',
            'correo.email' => 'El correo no es válido.',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $clave = $this->pedirClave();
        if ($clave === null) {
            return self::FAILURE;
        }

        Usuario::create($datos + ['password' => $clave]);
        $this->components->info("Usuario «{$datos['correo']}» creado. Ya puede entrar a la SPA.");

        return self::SUCCESS;
    }
}
