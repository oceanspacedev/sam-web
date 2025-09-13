<?php

namespace Database\Factories;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutletFactory extends Factory
{
    protected $model = Outlet::class;

    public function definition(): array
    {
        return [
            'kode_outlet' => 'OUT-'.$this->faker->unique()->numerify('###'),
            'nama_outlet' => $this->faker->company(),
            'alamat_outlet' => $this->faker->address(),
            'badanusaha_id' => BadanUsaha::factory(),
            'divisi_id' => Division::factory(),
            'region_id' => Region::factory(),
            'cluster_id' => Cluster::factory(),
            'distric' => 'D01',
            'status_outlet' => 'MAINTAIN',
        ];
    }
}

