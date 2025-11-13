<?php

namespace Tests\Feature;

use App\Filament\Resources\PlanVisits\Pages\ListPlanVisits;
use App\Imports\PlanVisitImport;
use App\Jobs\CleanupUploadedImportFile;
use App\Models\User;
use App\Support\StorageDisk;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PlanVisitImportActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_action_queues_background_job(): void
    {
        Excel::fake();

        $disk = StorageDisk::default();
        Storage::fake($disk);

        Gate::define('create_plan::visit', fn (): bool => true);
        Gate::define('export_plan::visit', fn (): bool => true);

        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);

        $filePath = 'import/plan-visit.xlsx';
        Storage::disk($disk)->put($filePath, 'dummy');

        Filament::setCurrentPanel('app');

        $page = app(ListPlanVisits::class);
        $page->mount();
        $page->cacheInteractsWithHeaderActions();

        $importAction = collect($page->getCachedHeaderActions())
            ->first(function ($action) {
                return $action->getName() === 'import';
            });

        $action = $importAction->getActionFunction();
        $this->assertNotNull($action);

        $action(['file' => $filePath]);

        Excel::assertQueued($filePath, $disk, function ($import): bool {
            return $import instanceof PlanVisitImport;
        });

        Excel::assertQueuedWithChain([
            new CleanupUploadedImportFile($disk, $filePath),
        ]);
    }
}
