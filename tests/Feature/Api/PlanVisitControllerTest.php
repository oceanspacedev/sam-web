<?php

namespace Tests\Feature\Api;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanVisitControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_returns_formatted_tanggal_visit(): void
    {
        $user = User::factory()->create();
        $outlet = Outlet::factory()->create();

        PlanVisit::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tanggal_visit' => Carbon::today(),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/planvisit');

        $response->assertOk();
        $response->assertJsonStructure([
            'meta' => ['code', 'status', 'message'],
            'data' => [
                ['id', 'user_id', 'outlet_id', 'tanggal_visit'],
            ],
        ]);

        $payload = $response->json('data')[0];
        $this->assertIsInt($payload['tanggal_visit']);
    }

    public function test_add_returns_new_record_with_formatted_date(): void
    {
        $user = User::factory()->create();
        $outlet = Outlet::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/planvisit', [
            'tanggal_visit' => Carbon::today()->toDateString(),
            'kode_outlet' => $outlet->kode_outlet,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'meta' => ['code', 'status', 'message'],
            'data' => ['id', 'user_id', 'outlet_id', 'tanggal_visit'],
        ]);

        $this->assertIsInt($response->json('data.tanggal_visit'));
    }
}
