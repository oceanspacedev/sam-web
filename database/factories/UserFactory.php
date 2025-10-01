<?php

namespace Database\Factories;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'username' => $this->faker->unique()->userName(),
            'nama_lengkap' => $this->faker->name(),
            'badanusaha_id' => BadanUsaha::factory(),
            'divisi_id' => Division::factory(),
            'region_id' => Region::factory(),
            'cluster_id' => Cluster::factory(),
            'role_id' => Role::factory(),
            // Temporarily set to 0; will be updated to self ID in afterCreating
            'tm_id' => 0,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function unverified()
    {
        return $this->state(function () {
            return [];
        });
    }

    /**
     * Indicate that the user should have a personal team.
     *
     * @return $this
     */
    public function withPersonalTeam()
    {
        return $this->state([]);
    }

    public function configure()
    {
        return $this->afterCreating(function (User $user) {
            // Ensure tm_id references an existing user; point to self by default
            $user->tm_id = $user->id;
            $user->save();
        });
    }
}
