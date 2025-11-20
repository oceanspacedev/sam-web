<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Outlets\Pages\ListOutlets;
use App\Filament\Resources\Outlets\Pages\ViewOutlet;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class OutletResourceViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view_any_outlet', 'view_outlet', 'update_outlet'] as $ability) {
            if (! Gate::has($ability)) {
                Gate::define($ability, fn () => true);
            }
        }
    }

    /** @test */
    public function can_render_outlet_view_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        /** @var Outlet $outlet */
        $outlet = Outlet::factory()->create();

        Livewire::test(ListOutlets::class)
            ->assertStatus(200);

        Livewire::test(ViewOutlet::class, ['record' => $outlet->getKey()])
            ->assertStatus(200)
            ->assertSee($outlet->nama_outlet);
    }
}
