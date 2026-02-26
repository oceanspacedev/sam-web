<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PlanVisitFactory extends Factory
{
    protected $model = PlanVisit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'visitable_type' => Outlet::class,
            'visitable_id' => Outlet::factory(),
            'tanggal_visit' => Carbon::now()->toDateString(),
            'realized_at' => null,
            'schedule_scope' => 'daily',
            'period_start' => Carbon::now()->startOfDay(),
            'period_end' => Carbon::now()->endOfDay(),
            'schedule_week' => Carbon::now()->weekOfYear,
            'schedule_year' => Carbon::now()->year,
        ];
    }

    public function forOutlet(Outlet|int $outlet): static
    {
        return $this->state(fn (array $attributes) => [
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet instanceof Outlet ? $outlet->id : $outlet,
        ]);
    }

    public function forUser(User|int $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user instanceof User ? $user->id : $user,
        ]);
    }

    public function realized(?Carbon $at = null): static
    {
        return $this->state(fn (array $attributes) => [
            'realized_at' => $at ?? Carbon::now(),
        ]);
    }

    public function unplanned(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_scope' => 'unplanned',
        ]);
    }
}
