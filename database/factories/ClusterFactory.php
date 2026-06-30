<?php

namespace Database\Factories;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClusterFactory extends Factory
{
    protected $model = Cluster::class;

    public function definition(): array
    {
        $suffix = $this->faker->unique()->numerify('###');

        return [
            'code' => 'CLUS_'.$suffix,
            'name' => 'Cluster '.$suffix,
            'badanusaha_id' => BadanUsaha::factory(),
            'divisi_id' => Division::factory(),
            'region_id' => Region::factory(),
        ];
    }
}
