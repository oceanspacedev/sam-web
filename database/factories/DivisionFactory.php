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
        $suffix = $this->faker->unique()->numerify('###');

        return [
            'code' => 'DIV_'.$suffix,
            'name' => 'Divisi '.$suffix,
            'badanusaha_id' => BadanUsaha::factory(),
        ];
    }
}
