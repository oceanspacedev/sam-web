<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class RoleResourceParentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view_any_role', 'view_role', 'create_role', 'update_role'] as $ability) {
            if (! Gate::has($ability)) {
                Gate::define($ability, fn () => true);
            }
        }
    }

    /** @test */
    public function can_assign_parent_role_when_creating(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $parent = Role::factory()->create([
            'organizational_scope_level' => 'cluster',
        ]);

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'Child Role',
                'parent_role_id' => $parent->id,
                'can_access_web' => true,
                'organizational_scope_level' => 'cluster',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Role::class, [
            'name' => 'Child Role',
            'parent_role_id' => $parent->id,
        ]);
    }

    /** @test */
    public function can_update_parent_role_when_editing(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $initialParent = Role::factory()->create([
            'organizational_scope_level' => 'cluster',
        ]);

        $newParent = Role::factory()->create([
            'organizational_scope_level' => 'cluster',
        ]);

        $child = Role::factory()->create([
            'parent_role_id' => $initialParent->id,
            'organizational_scope_level' => 'cluster',
        ]);

        Livewire::test(EditRole::class, ['record' => $child->getKey()])
            ->fillForm([
                'name' => $child->name,
                'parent_role_id' => $newParent->id,
                'can_access_web' => $child->can_access_web,
                'organizational_scope_level' => 'cluster',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Role::class, [
            'id' => $child->id,
            'parent_role_id' => $newParent->id,
        ]);
    }
}
