<?php

namespace Database\Factories;

use App\Models\BadanUsaha;
use Illuminate\Database\Eloquent\Factories\Factory;

class BadanUsahaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BadanUsaha::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => 'BU-'.implode(' ', $this->faker->unique()->words(2)),
        ];
    }
}
