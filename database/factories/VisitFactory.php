<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'tanggal_visit' => Carbon::now()->toDateString(),
            'user_id' => User::factory(),
            'outlet_id' => Outlet::factory(),
            'tipe_visit' => 'ROUTINE',
            'latlong_in' => '0,0',
            'check_in_time' => Carbon::now(),
        ];
    }
}

