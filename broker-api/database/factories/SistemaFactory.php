<?php

namespace Database\Factories;

use App\Models\Sistema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sistema>
 */
class SistemaFactory extends Factory
{
    protected $model = Sistema::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->regexify('[a-z]{6}'),
            'nombre' => 'Sistema '.fake()->word(),
            'responsable' => fake()->safeEmail(),
        ];
    }

    public function suspendido(): static
    {
        return $this->state(fn () => ['suspendido_at' => now()]);
    }
}
