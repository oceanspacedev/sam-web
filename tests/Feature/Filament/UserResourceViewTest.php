<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceViewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_render_user_view_page()
    {
        // Allow user permissions for this test
        Gate::define('view_any_user', fn () => true);
        Gate::define('view_user', fn () => true);
        Gate::define('update_user', fn () => true);

        $user = User::factory()->create();

        // Seed some relations
        $outlet = Outlet::factory()->create([
            'divisi_id' => $user->divisi_id,
            'region_id' => $user->region_id,
            'cluster_id' => $user->cluster_id,
        ]);

        Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]);

        // Create a PlanVisit minimally if the table exists
        if (class_exists(PlanVisit::class)) {
            PlanVisit::query()->create([
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'tanggal_visit' => now()->toDateString(),
            ]);
        }

        $this->actingAs($user);

        // Ensure list page loads
        Livewire::test(ListUsers::class)
            ->assertStatus(200);

        // Ensure view page loads
        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->assertStatus(200)
            ->assertSee($user->nama_lengkap);
    }
}
