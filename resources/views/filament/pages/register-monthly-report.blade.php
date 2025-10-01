@php /** @var App\Filament\Pages\RegisterMonthlyReport $this */ @endphp
<x-filament::page>
    <div>
        {{ $this->form }}
    </div>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament::page>
