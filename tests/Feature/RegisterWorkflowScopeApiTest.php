<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('s3');

    $this->withoutMiddleware(\App\Http\Middleware\RateLimitUploads::class);
});

function registerWorkflowScopeHierarchy(string $suffix): array
{
    $badanUsaha = BadanUsaha::create(['code' => 'BU_'.$suffix, 'name' => 'BU-'.$suffix]);
    $division = Division::create(['code' => 'DIV_'.$suffix, 'name' => 'DIV-'.$suffix, 'badanusaha_id' => $badanUsaha->id]);
    $region = Region::create(['code' => 'REG_'.$suffix, 'name' => 'REG-'.$suffix, 'badanusaha_id' => $badanUsaha->id, 'divisi_id' => $division->id]);
    $cluster = Cluster::create(['code' => 'CLUS_'.$suffix, 'name' => 'CLUS-'.$suffix, 'badanusaha_id' => $badanUsaha->id, 'divisi_id' => $division->id, 'region_id' => $region->id]);

    return ['bu' => $badanUsaha, 'div' => $division, 'reg' => $region, 'clus' => $cluster];
}

function registerWorkflowScopeUser(array $hierarchy, array $permissions = []): User
{
    $role = Role::create([
        'name' => 'REGISTER_SCOPE_'.uniqid(),
        'guard_name' => 'web',
        'can_access_web' => true,
        'organizational_scope_level' => 'cluster',
    ]);

    foreach ($permissions as $permissionName) {
        $permission = Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
    }

    $user = User::factory()->create(['role_id' => $role->id]);
    $user->assignRole($role);
    $user->badanUsahas()->attach($hierarchy['bu']->id);
    $user->divisis()->attach($hierarchy['div']->id);
    $user->regions()->attach($hierarchy['reg']->id);
    $user->clusters()->attach($hierarchy['clus']->id);

    return $user->fresh();
}

function registerWorkflowScopeRegister(array $hierarchy, User $owner, array $overrides = []): Register
{
    return Register::create(array_merge([
        'nama_outlet' => 'Register Scope '.uniqid(),
        'alamat_outlet' => 'Jl. Scope',
        'nama_pemilik_outlet' => 'Owner Scope',
        'nomer_tlp_outlet' => '081234567890',
        'ktp_outlet' => '1234567890123456',
        'distric' => 'DISTRICT',
        'latlong' => '-6.2000,106.8000',
        'oppo' => '0',
        'vivo' => '0',
        'samsung' => '0',
        'xiaomi' => '0',
        'realme' => '0',
        'fl' => '0',
        'poto_shop_sign' => 'register/photos/sign.jpg',
        'poto_depan' => 'register/photos/front.jpg',
        'poto_kiri' => 'register/photos/left.jpg',
        'poto_kanan' => 'register/photos/right.jpg',
        'poto_ktp' => 'register/ktp/ktp.jpg',
        'video' => 'register/videos/video.mp4',
        'created_by_id' => $owner->id,
        'tm_id' => $owner->id,
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'type' => 'NOO',
        'status' => 'PENDING',
        'keterangan' => null,
    ], $overrides));
}

test('register workflow mutations are rejected when target is outside actor register scope', function (string $method, string $path, array $payload, array $registerOverrides): void {
    $actorHierarchy = registerWorkflowScopeHierarchy('actor_'.uniqid());
    $outsideHierarchy = registerWorkflowScopeHierarchy('outside_'.uniqid());

    $actor = registerWorkflowScopeUser($actorHierarchy, [
        'Confirm:Register',
        'Approve:Register',
        'Reject:Register',
    ]);
    $owner = registerWorkflowScopeUser($outsideHierarchy);
    $register = registerWorkflowScopeRegister($outsideHierarchy, $owner, $registerOverrides);
    $original = $register->only(['status', 'kode_outlet', 'limit', 'confirmed_by_id', 'approved_by_id', 'rejected_by_id', 'keterangan']);

    $response = $this->actingAs($actor, 'sanctum')
        ->json($method, str_replace('{id}', (string) $register->id, $path), array_merge(['id' => $register->id], $payload));

    $response
        ->assertStatus(403)
        ->assertJsonPath('meta.status', 'error');

    $register->refresh();

    expect($register->only(['status', 'kode_outlet', 'limit', 'confirmed_by_id', 'approved_by_id', 'rejected_by_id', 'keterangan']))
        ->toBe($original);
})->with([
    'confirm' => [
        'PATCH',
        '/api/registers/{id}/confirm',
        ['status' => 'CONFIRMED', 'kode_outlet' => 'OUT-SCOPE', 'limit' => 1000000],
        ['type' => 'NOO', 'status' => 'PENDING'],
    ],
    'approve' => [
        'PATCH',
        '/api/registers/{id}/approve',
        ['status' => 'APPROVED'],
        ['type' => 'NOO', 'status' => 'CONFIRMED', 'kode_outlet' => 'OUT-SCOPE', 'limit' => 1000000],
    ],
    'reject' => [
        'PATCH',
        '/api/registers/{id}/reject',
        ['status' => 'REJECTED', 'alasan' => 'Di luar scope'],
        ['type' => 'NOO', 'status' => 'PENDING'],
    ],
]);

test('lead upgrade is rejected when target is outside actor register scope', function (): void {
    $actorHierarchy = registerWorkflowScopeHierarchy('actor_upgrade');
    $outsideHierarchy = registerWorkflowScopeHierarchy('outside_upgrade');

    $actor = registerWorkflowScopeUser($actorHierarchy);
    $owner = registerWorkflowScopeUser($outsideHierarchy);
    $lead = registerWorkflowScopeRegister($outsideHierarchy, $owner, [
        'type' => 'LEAD',
        'status' => 'PENDING',
        'ktp_outlet' => '-',
        'poto_ktp' => '-',
    ]);

    $response = $this->actingAs($actor, 'sanctum')
        ->patchJson("/api/registers/{$lead->id}/upgrade", [
            'id' => $lead->id,
            'noktp' => '1234567890123456',
            'photo' => UploadedFile::fake()->image('ktp.jpg', 800, 600),
        ]);

    $response
        ->assertStatus(403)
        ->assertJsonPath('meta.status', 'error');

    $lead->refresh();

    expect($lead->type)->toBe('LEAD')
        ->and($lead->ktp_outlet)->toBe('-')
        ->and($lead->poto_ktp)->toBe('-');
});
