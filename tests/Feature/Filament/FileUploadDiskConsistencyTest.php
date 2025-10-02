<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Outlets\OutletResource;
use App\Filament\Resources\Registers\RegisterResource;
use App\Filament\Resources\Visits\VisitResource;
use App\Support\StorageDisk;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
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
     * @param  callable(ComponentContainer): ComponentContainer  $callback
     */
    protected function makeForm(callable $callback): ComponentContainer
    {
        $livewire = new class extends LivewireComponent implements HasForms
        {
            use InteractsWithForms;

            public function render(): string
            {
                return '';
            }
        };

        return $callback(Form::make($livewire));
    }

    protected function gatherFileUploadComponents(ComponentContainer $container): Collection
    {
        return collect($container->getComponents())
            ->flatMap(fn (Component $component): array => $this->flattenComponent($component))
            ->filter(fn (Component $component): bool => $component instanceof FileUpload)
            ->values();
    }

    /**
     * @return array<int, Component>
     */
    protected function flattenComponent(Component $component): array
    {
        $components = [$component];

        if (method_exists($component, 'getChildComponentContainer') && ($childContainer = $component->getChildComponentContainer())) {
            foreach ($childContainer->getComponents() as $childComponent) {
                $components = array_merge($components, $this->flattenComponent($childComponent));
            }
        }

        return $components;
    }
}
