<?php

namespace Tests\Feature\Api;

use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncOutletResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_outlet_by_kode_outlet_and_username(): void
    {
        Storage::fake('public');

        $user = \App\Models\User::factory()->create([
            'username' => 'ag1',
        ]);

        $outlet = Outlet::factory()->create([
            'divisi_id' => $user->divisi_id,
            'nama_pemilik_outlet' => 'John Owner',
            'nomer_tlp_outlet' => '081234',
            'latlong' => '1,1',
            'poto_shop_sign' => 'outlets/shop.jpg',
            'poto_depan' => 'outlets/front.jpg',
            'poto_kiri' => 'outlets/left.jpg',
            'poto_kanan' => 'outlets/right.jpg',
            'poto_ktp' => 'outlets/ktp.jpg',
            'video' => 'outlets/video.mp4',
        ]);

        // ensure files exist in fake storage
        foreach (['poto_shop_sign', 'poto_depan', 'poto_kiri', 'poto_kanan', 'poto_ktp', 'video'] as $f) {
            Storage::disk('public')->put($outlet->$f, 'dummy');
        }

        $res = $this->postJson('/api/sync/outlet/reset', [
            'kode_outlet' => $outlet->kode_outlet,
            'username' => $user->username,
        ]);
        $res->assertOk();

        $outlet->refresh();
        $this->assertNull($outlet->nama_pemilik_outlet);
        $this->assertNull($outlet->nomer_tlp_outlet);
        $this->assertNull($outlet->latlong);
        $this->assertNull($outlet->poto_shop_sign);
        $this->assertNull($outlet->poto_depan);
        $this->assertNull($outlet->poto_kiri);
        $this->assertNull($outlet->poto_kanan);
        $this->assertNull($outlet->poto_ktp);
        $this->assertNull($outlet->video);
    }

    public function test_reset_outlet_by_kode_outlet_requires_username(): void
    {
        Storage::fake('public');
        $outlet = Outlet::factory()->create();

        // missing username should be 422
        $this->postJson('/api/sync/outlet/reset', ['kode_outlet' => $outlet->kode_outlet])
            ->assertStatus(422);
    }

    public function test_reset_outlet_not_found(): void
    {
        $this->postJson('/api/sync/outlet/reset', ['kode_outlet' => 'NOT-EXIST', 'username' => 'xx'])
            ->assertStatus(404);
    }
}
