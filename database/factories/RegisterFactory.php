<?php

namespace Database\Factories;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Register;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegisterFactory extends Factory
{
    protected $model = Register::class;

    public function definition(): array
    {
        $name = 'REGISTER-'.$this->faker->unique()->numerify('###');

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
            'poto_shop_sign' => 'register/photos/sign.jpg',
            'poto_depan' => 'register/photos/front.jpg',
            'poto_kiri' => 'register/photos/left.jpg',
            'poto_kanan' => 'register/photos/right.jpg',
            'poto_ktp' => 'register/ktp/ktp.jpg',
            'video' => 'register/videos/vid.mp4',
            'oppo' => '0',
            'vivo' => '0',
            'realme' => '0',
            'samsung' => '0',
            'xiaomi' => '0',
            'fl' => '0',
            'latlong' => '0,0',
            'limit' => 0,
            'status' => 'PENDING',
            'type' => 'NOO',
            'created_by_id' => User::factory(),
            'tm_id' => User::factory(),
        ];
    }

    public function noo(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'NOO',
        ]);
    }

    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'LEAD',
        ]);
    }
}
