<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Visits\Pages\ListVisits;
use App\Models\User;
use App\Models\Visit;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class VisitResourceBadgeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function visit_type_column_uses_distinct_badge_colors(): void
    {
        Gate::define('view_any_visit', fn () => true);
        Gate::define('view_visit', fn () => true);
        Gate::define('create_visit', fn () => true);
        Gate::define('update_visit', fn () => true);
        Gate::define('delete_visit', fn () => true);
        Gate::define('delete_any_visit', fn () => true);
        Gate::define('restore_any_visit', fn () => true);
        Gate::define('force_delete_any_visit', fn () => true);
        Gate::define('force_delete_visit', fn () => true);

        $user = User::factory()->create();

        $this->actingAs($user);

        Visit::factory()->create([
            'user_id' => $user->id,
            'tipe_visit' => 'PLANNED',
            'transaksi' => 'YES',
        ]);

        Visit::factory()->create([
            'user_id' => $user->id,
            'tipe_visit' => 'EXTRACALL',
            'transaksi' => 'NO',
        ]);

        Livewire::test(ListVisits::class)
            ->assertStatus(200)
            ->assertTableColumnExists('tipe_visit', function ($column): bool {
                if (! $column instanceof TextColumn) {
                    return false;
                }

                return $column->isBadge()
                    && $column->getColor('PLANNED') === 'primary'
                    && $column->getColor('EXTRACALL') === 'info'
                    && $column->getColor('UNKNOWN') === 'gray';
            })
            ->assertTableColumnExists('transaksi', function ($column): bool {
                if (! $column instanceof TextColumn) {
                    return false;
                }

                return $column->isBadge()
                    && $column->getColor('YES') === 'success'
                    && $column->getColor('NO') === 'danger'
                    && $column->getColor('UNKNOWN') === 'gray';
            });
    }
}
