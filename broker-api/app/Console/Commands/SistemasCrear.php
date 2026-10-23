<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GestionaSistemas;
use App\Models\Sistema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SistemasCrear extends Command
{
    use GestionaSistemas;

    protected $signature = 'sistemas:crear
        {codigo : Código corto en minúsculas, p. ej. sgrh}
        {--nombre= : Nombre completo del sistema}
        {--responsable= : Correo del responsable técnico}';

    protected $description = 'Registra un sistema consumidor y genera su primera llave';

    public function handle(): int
    {
        $datos = [
            'codigo' => $this->argument('codigo'),
            'nombre' => $this->option('nombre') ?? $this->ask('Nombre del sistema'),
            'responsable' => $this->option('responsable'),
        ];

        $validador = Validator::make($datos, [
            'codigo' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{1,39}$/', 'unique:sistemas,codigo'],
            'nombre' => ['required', 'string', 'max:255'],
            'responsable' => ['nullable', 'email'],
        ], [
            'codigo.regex' => 'El código solo admite minúsculas, números y guiones (2 a 40 caracteres).',
            'codigo.unique' => 'Ya existe un sistema con ese código.',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        [$sistema, $llave] = DB::transaction(function () use ($datos) {
            $sistema = Sistema::create($datos);

            return [$sistema, $sistema->generarLlave()];
        });

        $this->components->info("Sistema «{$sistema->codigo}» registrado.");
        $this->mostrarLlave($sistema, $llave);

        return self::SUCCESS;
    }
}
