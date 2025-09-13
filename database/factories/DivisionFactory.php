<?php

namespace Database\Factories;

use App\Models\BadanUsaha;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        return [
            'name' => 'DIV-'.$this->faker->unique()->word(),
            'badanusaha_id' => BadanUsaha::factory(),
        ];
    }
}

