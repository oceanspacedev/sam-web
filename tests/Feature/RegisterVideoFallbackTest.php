<?php

use App\Filament\Resources\Registers\Pages\CreateRegister;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

it('stores dash when video is not uploaded via the Filament create form', function (): void {
    Filament::setCurrentPanel('admin');

    $permissions = collect([
        'ViewAny:Register',
        'Create:Register',
    ])->map(fn (string $name): Permission => Permission::firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]));

    $adminRole = Role::factory()->create([
        'name' => 'SUPER ADMIN',
        'can_access_web' => true,
        'organizational_scope_level' => 'all',
    ]);

    $adminRole->syncPermissions($permissions);

    $user = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $user->assignRole($adminRole);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user);

    $badanUsaha = BadanUsaha::factory()->create();
    $division = Division::factory()->create(['badanusaha_id' => $badanUsaha->id]);
    $region = Region::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    Livewire::test(CreateRegister::class)
        ->fillForm([
            'nama_outlet' => 'Outlet Test',
            'distric' => 'D01',
            'alamat_outlet' => 'Jalan Test',
            'nama_pemilik_outlet' => 'Pemilik Test',
            'nomer_tlp_outlet' => '08123',
            'latlong' => '0,0',
            'ktp_outlet' => fake()->numerify('################'),
            'poto_shop_sign' => UploadedFile::fake()->image('sign.jpg'),
            'poto_depan' => UploadedFile::fake()->image('front.jpg'),
            'poto_kiri' => UploadedFile::fake()->image('left.jpg'),
            'poto_kanan' => UploadedFile::fake()->image('right.jpg'),
            'poto_ktp' => UploadedFile::fake()->image('ktp.jpg'),

            'oppo' => 0,
            'vivo' => 0,
            'realme' => 0,
            'samsung' => 0,
            'xiaomi' => 0,
            'fl' => 0,

            'created_by_id' => $user->id,
            'type' => 'NOO',

            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,

            'tm_id' => $user->id,

            // IMPORTANT: omit 'video' entirely to simulate no upload
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $register = Register::query()->latest('id')->firstOrFail();

    expect($register->video)->toBe('-');
});
