<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\OperationalDashboardData;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard Operasional';

    #[Url(as: 'range', history: true)]
    public ?string $range = null;

    public function mount(): void
    {
        // #[Url] menghidrasi $this->range dari query string sebelum mount();
        // validasi agar nilai invalid (mis. ?range=foo) tidak menyisakan tombol
        // range tanpa highlight padahal data difilter sebagai default.
        $this->range = $this->isValidRange($this->range)
            ? $this->range
            : $this->getDefaultRange();
    }

    private function isValidRange(?string $range): bool
    {
        return $range !== null && array_key_exists($range, $this->rangeOptions());
    }

    public function setRange(string $range): void
    {
        if (! array_key_exists($range, $this->rangeOptions())) {
            return;
        }

        $this->range = $range;
    }

    public function getDefaultRange(): string
    {
        return 'week';
    }

    /**
     * @return array<string, string>
     */
    public function rangeOptions(): array
    {
        return [
            'today' => 'Hari ini',
            'week' => '7 hari',
            'month' => '30 hari',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardData(): array
    {
        /** @var User|null $viewer */
        $viewer = Auth::user();

        if (! $viewer) {
            return [];
        }

        return app(OperationalDashboardData::class)
            ->for($viewer, $this->range ?? $this->getDefaultRange());
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getWidgets(): array
    {
        // The operational dashboard renders a single purpose-built view
        // (powered by OperationalDashboardData) instead of the widget grid.
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Html::make(fn (): string => view('filament.pages.dashboard', [
                'data' => $this->dashboardData(),
                'rangeOptions' => $this->rangeOptions(),
                'range' => $this->range ?? $this->getDefaultRange(),
            ])->render()),
        ]);
    }
}