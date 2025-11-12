<?php

namespace Tests\Feature;

use App\Filament\Resources\Outlets\Pages\ListOutlets;
use App\Imports\OutletImport;
use App\Jobs\CleanupUploadedImportFile;
use App\Models\User;
use App\Support\StorageDisk;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class OutletImportActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_action_queues_background_job(): void
    {
        Excel::fake();

        $disk = StorageDisk::default();
        Storage::fake($disk);

        Gate::define('create_outlet', fn (User $user): bool => true);
        Gate::define('export_outlet', fn (User $user): bool => true);

        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);

        $filePath = 'import/outlet-create.xlsx';
        Storage::disk($disk)->put($filePath, 'dummy');

        Filament::setCurrentPanel('app');

        $page = app(ListOutlets::class);
        $page->mount();
        $page->cacheInteractsWithHeaderActions();

        $importAction = collect($page->getCachedHeaderActions())
            ->first(fn ($action) => $action->getName() === 'import');

        $this->assertNotNull($importAction);

        $actionClosure = $importAction->getActionFunction();
        $this->assertNotNull($actionClosure);

        $actionClosure([
            'mode' => 'create',
            'file_create' => $filePath,
            'file_update' => null,
        ]);

        Excel::assertQueued($filePath, $disk, function ($import): bool {
            return $import instanceof OutletImport;
        });

        Excel::assertQueuedWithChain([
            new CleanupUploadedImportFile($disk, $filePath),
        ]);
    }
}
