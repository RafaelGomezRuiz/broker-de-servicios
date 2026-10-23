<?php

namespace Database\Seeders;

use App\Models\Sistema;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos para desarrollo local: el sistema «pruebas» con su llave,
     * para probar la API desde Postman. Nunca corre en producción.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('El seeder es solo para desarrollo. En producción usa sistemas:crear.');

            return;
        }

        if (Sistema::where('codigo', 'pruebas')->exists()) {
            $this->command->info('El sistema «pruebas» ya existe. Para otra llave: php artisan sistemas:nueva-llave pruebas');

            return;
        }

        $sistema = Sistema::create([
            'codigo' => 'pruebas',
            'nombre' => 'Pruebas de desarrollo',
        ]);
        $llave = $sistema->generarLlave();

        $this->command->warn('Llave del sistema «pruebas» (cópiala en Postman como api_key, no se volverá a mostrar):');
        $this->command->line("  {$llave->plainTextToken}");
    }
}
