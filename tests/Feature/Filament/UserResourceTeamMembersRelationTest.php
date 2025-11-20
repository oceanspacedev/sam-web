<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\TeamMembersRelationManager;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTeamMembersRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view_any_user', 'view_user', 'update_user'] as $ability) {
            if (! Gate::has($ability)) {
                Gate::define($ability, fn () => true);
            }
        }
    }

    /** @test */
    public function team_members_relation_lists_direct_reports(): void
    {
        /** @var User $viewer */
        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        /** @var Role $tmRole */
        $tmRole = Role::factory()->create([
            'name' => 'TM',
        ]);

        /** @var User $tm */
        $tm = User::factory()->create([
            'role_id' => $tmRole->id,
        ]);

        /** @var Role $childRole */
        $childRole = Role::factory()->create([
            'name' => 'Sales',
            'parent_role_id' => $tmRole->id,
        ]);

        /** @var User $teamMember */
        $teamMember = User::factory()->create([
            'role_id' => $childRole->id,
            'tm_id' => $tm->id,
        ]);

        Livewire::test(TeamMembersRelationManager::class, [
            'ownerRecord' => $tm,
            'pageClass' => ViewUser::class,
        ])
            ->assertCanSeeTableRecords([$teamMember])
            ->assertCanNotSeeTableRecords([$viewer]);
    }
}
