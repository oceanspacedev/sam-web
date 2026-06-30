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
        $suffix = $this->faker->unique()->numerify('###');

        return [
            'code' => 'REG_'.$suffix,
            'name' => 'Region '.$suffix,
            'badanusaha_id' => BadanUsaha::factory(),
            'divisi_id' => Division::factory(),
        ];
    }
}
