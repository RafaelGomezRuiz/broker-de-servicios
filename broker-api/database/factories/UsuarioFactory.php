<?php

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'password' => 'clave-de-prueba-segura',
        ];
    }

    public function desactivado(): static
    {
        return $this->state(fn () => ['desactivado_at' => now()]);
    }
}
