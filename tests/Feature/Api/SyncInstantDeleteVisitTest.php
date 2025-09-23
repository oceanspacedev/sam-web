<?php

namespace Tests\Feature\Api;

use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SyncInstantDeleteVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_duplicate_preferring_missing_out(): void
    {
        $user = User::factory()->create(['username' => 'tester']);
        $outlet = Outlet::factory()->create();

        // Two visits same day, same outlet
        Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tanggal_visit' => Carbon::now()->startOfDay(),
            'latlong_in' => '1,1',
            'latlong_out' => null,
            'check_in_time' => Carbon::now()->subHour(),
            'check_out_time' => null,
        ]);
        $kept = Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tanggal_visit' => Carbon::now()->startOfDay(),
            'latlong_in' => '1,1',
            'latlong_out' => '1,1',
            'check_in_time' => Carbon::now()->subHour(),
            'check_out_time' => Carbon::now(),
        ]);

        $this->postJson('/api/sync/visit/instant-delete', [
            'username' => 'tester',
        ])->assertOk()->assertJsonPath('data.deleted_total', 1);

        $this->assertDatabaseHas('visits', ['id' => $kept->id]);
        $this->assertEquals(1, Visit::where('user_id', $user->id)->where('outlet_id', $outlet->id)->whereDate('tanggal_visit', Carbon::now()->toDateString())->count());
    }

    public function test_deletes_extras_when_both_filled(): void
    {
        $user = User::factory()->create(['username' => 'guru']);
        $outlet = Outlet::factory()->create();

        $v1 = Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tanggal_visit' => Carbon::now()->startOfDay(),
            'latlong_out' => '2,2',
            'check_out_time' => Carbon::now(),
        ]);
        $v2 = Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tanggal_visit' => Carbon::now()->startOfDay(),
            'latlong_out' => '2,2',
            'check_out_time' => Carbon::now(),
        ]);

        $this->postJson('/api/sync/visit/instant-delete', [
            'username' => 'guru',
        ])->assertOk()->assertJson(fn ($json) => $json->where('data.deleted_total', 1)->etc());

        $this->assertEquals(1, Visit::where('user_id', $user->id)->where('outlet_id', $outlet->id)->whereDate('tanggal_visit', Carbon::now()->toDateString())->count());
        $this->assertTrue(Visit::whereKey($v1->id)->exists() xor Visit::whereKey($v2->id)->exists());
    }

    public function test_no_action_when_no_duplicates(): void
    {
        $user = User::factory()->create(['username' => 'solo']);
        $outlet = Outlet::factory()->create();

        Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tanggal_visit' => Carbon::now()->startOfDay(),
        ]);

        $this->postJson('/api/sync/visit/instant-delete', [
            'username' => 'solo',
        ])->assertOk()->assertJsonPath('data.deleted_total', 0);
    }
}
