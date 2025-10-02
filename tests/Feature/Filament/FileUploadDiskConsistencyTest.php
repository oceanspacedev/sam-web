<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Outlets\OutletResource;
use App\Filament\Resources\Registers\RegisterResource;
use App\Filament\Resources\Visits\VisitResource;
use App\Support\StorageDisk;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Livewire\Component as LivewireComponent;
use Tests\TestCase;

class FileUploadDiskConsistencyTest extends TestCase
{
    /**
     * @dataProvider resourceFormProvider
     */
    public function test_form_file_uploads_use_configured_disk(string $resourceClass): void
    {
        config(['filesystems.default' => 's3']);

        $form = $this->makeForm([$resourceClass, 'form']);
        $fileUploads = $this->gatherFileUploadComponents($form);

        $this->assertNotEmpty($fileUploads, sprintf('Expected at least one FileUpload component on %s form.', $resourceClass));

        $fileUploads->each(function (FileUpload $component): void {
            $this->assertSame(StorageDisk::default(), $component->getDiskName());
        });
    }

    public static function resourceFormProvider(): array
    {
        return [
            [OutletResource::class],
            [RegisterResource::class],
            [VisitResource::class],
        ];
    }

    /**
     * @param  callable(Schema): Schema  $callback
     */
    protected function makeForm(callable $callback): Schema
    {
        $livewire = new class extends LivewireComponent implements HasForms
        {
            use InteractsWithForms;

            public function render(): string
            {
                return '';
            }
        };

        return $callback(Schema::make($livewire));
    }

    protected function gatherFileUploadComponents(Schema $schema): Collection
    {
        return collect($this->flattenSchema($schema))
            ->values();
    }

    /**
     * @return array<int, FileUpload>
     */
    protected function flattenSchema(Schema $schema): array
    {
        $components = [];

        foreach ($schema->getComponents(withActions: false, withHidden: true) as $component) {
            if (! $component instanceof SchemaComponent) {
                continue;
            }

            if ($component instanceof FileUpload) {
                $components[] = $component;
            }

            foreach ($component->getChildSchemas(withHidden: true) as $childSchema) {
                $components = array_merge($components, $this->flattenSchema($childSchema));
            }
        }

        return $components;
    }
}
