<?php

namespace App\Filament\Pages;

use App\Models\BadanUsaha;
use App\Models\Division;
use App\Models\Register;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RegisterMonthlyReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static string $view = 'filament.pages.register-monthly-report';

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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filter Bulanan')
                    ->schema([
                        Forms\Components\Select::make('month')
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
                        Forms\Components\Select::make('year')
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
                        Forms\Components\Select::make('badanusahaId')
                            ->label('Badan Usaha')
                            ->options(fn () => BadanUsaha::orderBy('name', 'asc')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->placeholder('Semua'),
                        Forms\Components\Select::make('divisionId')
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
                Forms\Components\Section::make('Ringkasan')
                    ->schema([
                        Forms\Components\Placeholder::make('month_name')
                            ->label('Bulan')
                            ->content(fn (): string => $this->getMonthName()),
                        Forms\Components\Placeholder::make('register_total')
                            ->label('Total Register Bulan Ini')
                            ->content(fn (): string => number_format($this->getRegisterCount())),
                        Forms\Components\Placeholder::make('workdays')
                            ->label('Hari Kerja (tanpa Minggu)')
                            ->content(fn (): string => (string) $this->getRemainingDays()),
                        Forms\Components\Placeholder::make('avg_per_day')
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
                Tables\Columns\TextColumn::make('created_by')
                    ->label('Pembuat')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total Register')
                    ->sortable(),
            ])
            ->defaultSort('total', 'desc')
            ->paginated(false);
    }

    protected function getAggregatedQuery(): Builder
    {
        // Build an aggregate query grouped by created_by for the selected month/year
        return Register::query()
            ->withTrashed()
            ->select([
                DB::raw('users.nama_lengkap as user_name'),
                'noos.created_by',
                DB::raw('COUNT(noos.id) as total'),
            ])
            ->leftJoin('users', 'users.id', '=', 'noos.created_by')
            ->whereYear('noos.created_at', $this->year)
            ->whereMonth('noos.created_at', $this->month)
            ->when($this->badanusahaId, fn ($q) => $q->where('noos.badanusaha_id', $this->badanusahaId))
            ->when($this->divisionId, fn ($q) => $q->where('noos.divisi_id', $this->divisionId))
            ->groupBy('noos.created_by', 'users.nama_lengkap');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $rows = $this->getAggregatedQuery()->get(['user_name', 'total']);
                    $filename = 'register-report-'.$this->year.'-'.str_pad((string) $this->month, 2, '0', STR_PAD_LEFT).'.csv';

                    $handle = fopen('php://temp', 'r+');
                    fputcsv($handle, ['Pembuat', 'Total Register']);
                    foreach ($rows as $row) {
                        fputcsv($handle, [$row->user_name, $row->total]);
                    }
                    rewind($handle);
                    $csv = stream_get_contents($handle);
                    fclose($handle);

                    return response($csv, 200, [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                    ]);
                }),
        ];
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
    public function getTableRecordKey($record): string
    {
        // Prefer created_by as a unique key per row, fallback to user_name.
        $base = $record->created_by ?? $record->user_name ?? spl_object_id($record);

        return (string) ($base.'-'.$this->year.'-'.$this->month);
    }
}
