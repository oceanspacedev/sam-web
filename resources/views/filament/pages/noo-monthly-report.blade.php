@php /** @var App\Filament\Pages\NooMonthlyReport $this */ @endphp
<x-filament::page>
    <div>
        {{ $this->form }}
    </div>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament::page>
