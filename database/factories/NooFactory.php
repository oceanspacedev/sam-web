<?php

namespace Database\Factories;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Noo;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NooFactory extends Factory
{
    protected $model = Noo::class;

    public function definition(): array
    {
        $name = 'NOO-'.$this->faker->unique()->numerify('###');
        return [
            'kode_outlet' => null,
            'badanusaha_id' => BadanUsaha::factory(),
            'divisi_id' => Division::factory(),
            'nama_outlet' => $name,
            'alamat_outlet' => $this->faker->address(),
            'nama_pemilik_outlet' => $this->faker->name(),
            'nomer_tlp_outlet' => '08123',
            'nomer_wakil_outlet' => null,
            'ktp_outlet' => '1234',
            'distric' => 'D01',
            'region_id' => Region::factory(),
            'cluster_id' => Cluster::factory(),
            'poto_shop_sign' => 'noo/photos/sign.jpg',
            'poto_depan' => 'noo/photos/front.jpg',
            'poto_kiri' => 'noo/photos/left.jpg',
            'poto_kanan' => 'noo/photos/right.jpg',
            'poto_ktp' => 'noo/ktp/ktp.jpg',
            'video' => 'noo/videos/vid.mp4',
            'oppo' => '0',
            'vivo' => '0',
            'realme' => '0',
            'samsung' => '0',
            'xiaomi' => '0',
            'fl' => '0',
            'latlong' => '0,0',
            'limit' => 0,
            'status' => 'PENDING',
            'created_by' => 'Seeder',
            'tm_id' => User::factory(),
        ];
    }
}
