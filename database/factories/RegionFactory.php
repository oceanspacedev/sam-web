<?php

namespace Database\Factories;

use App\Models\BadanUsaha;
use App\Models\Division;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'name' => 'REG-'.$this->faker->unique()->word(),
            'badanusaha_id' => BadanUsaha::factory(),
            'divisi_id' => Division::factory(),
        ];
    }
}
