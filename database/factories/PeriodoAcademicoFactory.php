<?php

namespace Database\Factories;

use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;

/**
 * @extends Factory<PeriodoAcademico>
 */
class PeriodoAcademicoFactory extends Factory
{
    /**
     * @var class-string<PeriodoAcademico>
     */
    protected $model = PeriodoAcademico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->numberBetween(2000, 2100).'-'.fake()->unique()->numberBetween(2101, 2200),
            'starts_on' => $startsOn,
            'ends_on' => (clone $startsOn)->modify('+10 months'),
            'is_active' => false,
        ];
    }

    /**
     * Indicate that the period is the school's active cycle.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
