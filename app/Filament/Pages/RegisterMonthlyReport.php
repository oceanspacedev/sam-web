<?php

namespace App\Filament\Pages;

use App\Models\BadanUsaha;
use App\Models\Division;
use App\Models\Register;
use Carbon\Carbon;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RegisterMonthlyReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.pages.register-monthly-report';

    protected static ?string $navigationLabel = 'Register Monthly Report';

    protected static ?string $title = 'Register Monthly Report';

    /**
     * 1..12
     *
     * @var int
     */
    public $month;

    /**
     * yyyy
     *
     * @var int
     */
    public $year;

    /**
     * @var int|null
     */
    public $badanusahaId = null;

    /**
     * @var int|null
     */
    public $divisionId = null;

    public function mount(): void
    {
        // Default ke bulan lalu
        $lastMonth = Carbon::now()->subMonth();
        $this->month = (int) $lastMonth->format('m');
        $this->year = (int) $lastMonth->format('Y');
    }

    public function updatedMonth(): void
    {
        $this->dispatch('$refresh');
    }

    public function updatedYear(): void
    {
        $this->dispatch('$refresh');
    }

    public function updatedBadanusahaId(): void
    {
        // Reset dependent field when BU changes
        $this->divisionId = null;
        $this->dispatch('$refresh');
    }

    public function updatedDivisionId(): void
    {
        $this->dispatch('$refresh');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter Bulanan')
                    ->schema([
                        Select::make('month')
                            ->label('Bulan')
                            ->options([
                                1 => 'Januari',
                                2 => 'Februari',
                                3 => 'Maret',
                                4 => 'April',
                                5 => 'Mei',
                                6 => 'Juni',
                                7 => 'Juli',
                                8 => 'Agustus',
                                9 => 'September',
                                10 => 'Oktober',
                                11 => 'November',
                                12 => 'Desember',
                            ])
                            ->required()
                            ->reactive(),
                        Select::make('year')
                            ->label('Tahun')
                            ->options(function () {
                                $current = (int) date('Y');
                                $years = [];
                                for ($y = $current - 5; $y <= $current + 1; $y++) {
                                    $years[$y] = (string) $y;
                                }

                                return $years;
                            })
                            ->required()
                            ->reactive(),
                        Select::make('badanusahaId')
                            ->label('Badan Usaha')
                            ->options(fn () => BadanUsaha::orderBy('name', 'asc')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->placeholder('Semua'),
                        Select::make('divisionId')
                            ->label('Divisi')
                            ->options(function (callable $get) {
                                $bu = $get('badanusahaId');
                                $query = Division::query();
                                if ($bu) {
                                    $query->where('badanusaha_id', $bu);
                                }

                                return $query->orderBy('name', 'asc')->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->placeholder('Semua'),
                    ])
                    ->columns(4),
                Section::make('Ringkasan')
                    ->schema([
                        Placeholder::make('month_name')
                            ->label('Bulan')
                            ->content(fn (): string => $this->getMonthName()),
                        Placeholder::make('register_total')
                            ->label('Total Register Bulan Ini')
                            ->content(fn (): string => number_format($this->getRegisterCount())),
                        Placeholder::make('workdays')
                            ->label('Hari Kerja (tanpa Minggu)')
                            ->content(fn (): string => (string) $this->getRemainingDays()),
                        Placeholder::make('avg_per_day')
                            ->label('Rata-rata Register / Hari')
                            ->content(fn (): string => number_format($this->getRegisterAveragePerDay(), 2)),
                    ])
                    ->columns(4),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAggregatedQuery())
            ->columns([
                TextColumn::make('user_name')
                    ->label('Pembuat')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('total')
                    ->label('Total Register')
                    ->sortable(),
            ])
            ->defaultSort('total', 'desc')
            ->defaultKeySort(false)
            ->paginated(false);
    }

    protected function getAggregatedQuery(): Builder
    {
        // Build an aggregate query grouped by created_by for the selected month/year
        return Register::query()
            ->withTrashed()
            ->select([
                'registers.created_by',
                DB::raw('COALESCE(users.nama_lengkap, registers.created_by) as user_name'),
                DB::raw('COUNT(registers.id) as total'),
            ])
            ->leftJoin('users', 'users.id', '=', 'registers.created_by')
            ->whereYear('registers.created_at', $this->year)
            ->whereMonth('registers.created_at', $this->month)
            ->when($this->badanusahaId, fn ($q) => $q->where('registers.badanusaha_id', $this->badanusahaId))
            ->when($this->divisionId, fn ($q) => $q->where('registers.divisi_id', $this->divisionId))
            ->groupBy('registers.created_by', 'users.nama_lengkap');
    }

    public function getMonthName(): string
    {
        return Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    public function getDaysInMonth(): int
    {
        return cal_days_in_month(CAL_GREGORIAN, $this->month, $this->year);
    }

    public function getSundaysCount(): int
    {
        $daysInMonth = $this->getDaysInMonth();
        $sundays = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            if ((int) date('w', mktime(0, 0, 0, $this->month, $day, $this->year)) === 0) {
                $sundays++;
            }
        }

        return $sundays;
    }

    public function getRemainingDays(): int
    {
        return $this->getDaysInMonth() - $this->getSundaysCount();
    }

    public function getRegisterCount(): int
    {
        return Register::query()
            ->withTrashed()
            ->whereYear('created_at', $this->year)
            ->whereMonth('created_at', $this->month)
            ->when($this->badanusahaId, fn ($q) => $q->where('badanusaha_id', $this->badanusahaId))
            ->when($this->divisionId, fn ($q) => $q->where('divisi_id', $this->divisionId))
            ->count();
    }

    public function getRegisterAveragePerDay(): float
    {
        $remainingDays = max(1, $this->getRemainingDays());

        return ceil(($this->getRegisterCount() / $remainingDays) * 100) / 100;
    }

    /**
     * Provide a stable string key for each aggregated row.
     */
    public function getTableRecordKey(Model|array $record): string
    {
        // Prefer created_by as a unique key per row, fallback to user_name.
        $base = $record->created_by ?? $record->user_name ?? spl_object_id($record);

        return (string) ($base.'-'.$this->year.'-'.$this->month);
    }
}
