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
        $suffix = $this->faker->unique()->numerify('###');

        return [
            'code' => 'BU_'.$suffix,
            'name' => 'Badan Usaha '.$suffix,
        ];
    }
}
