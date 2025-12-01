<?php

namespace App\Filament\Pages;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Visit;
use App\Services\FileUploadService;
use App\Support\StorageDisk;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class LiveVisit extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Live Visit';

    protected static ?string $title = 'Live Visit';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.live-visit';

    // Step-based flow: 'select' -> 'photo' -> 'confirm'
    public string $checkinStep = 'select';

    // Check-in form data
    public ?int $checkin_outlet_id = null;

    public ?string $checkin_tipe = 'PLANNED';

    public ?string $checkin_latlong = null;

    public $checkin_photo = null;

    // Checkout form data
    public ?int $checkout_visit_id = null;

    public ?string $checkout_latlong = null;

    public $checkout_photo = null;

    public ?string $checkout_transaksi = 'NO';

    public ?string $checkout_laporan = null;

    // Search for extracall
    public string $outletSearch = '';

    // Location state
    public bool $locationReady = false;

    public ?string $locationError = null;

    // Validation state for modal dialogs
    public bool $showRadiusModal = false;

    public ?float $userDistance = null;

    public ?float $outletRadius = null;

    public ?string $outletLatlong = null;

    #[Computed]
    public function todayVisits(): Collection
    {
        return Visit::query()
            ->with('outlet:id,kode_outlet,nama_outlet,latlong,radius')
            ->where('user_id', Auth::id())
            ->whereDate('tanggal_visit', Carbon::today())
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function activeVisit(): ?Visit
    {
        return $this->todayVisits->whereNull('check_out_time')->first();
    }

    #[Computed]
    public function plannedOutlets(): Collection
    {
        $user = Auth::user();
        $today = Carbon::today();

        return PlanVisit::query()
            ->with('outlet:id,kode_outlet,nama_outlet,alamat_outlet,distric,latlong,radius')
            ->where('user_id', $user->id)
            ->whereNull('realized_at')
            ->where(function ($q) use ($today) {
                $q->where(function ($daily) use ($today) {
                    $daily->where('schedule_scope', 'daily')
                        ->whereDate('tanggal_visit', $today);
                })->orWhere(function ($weekly) use ($today) {
                    $weekly->where('schedule_scope', 'weekly')
                        ->whereDate('period_start', '<=', $today)
                        ->whereDate('period_end', '>=', $today);
                });
            })
            ->get()
            ->pluck('outlet')
            ->filter();
    }

    #[Computed]
    public function extracallOutlets(): Collection
    {
        $user = Auth::user();
        $query = Outlet::query()
            ->select(['id', 'kode_outlet', 'nama_outlet', 'alamat_outlet', 'distric', 'latlong', 'radius'])
            ->accessibleTo($user);

        if (! empty($this->outletSearch)) {
            $query->where(function ($q) {
                $q->where('nama_outlet', 'like', "%{$this->outletSearch}%")
                    ->orWhere('kode_outlet', 'like', "%{$this->outletSearch}%")
                    ->orWhere('alamat_outlet', 'like', "%{$this->outletSearch}%");
            });
        }

        return $query->limit(50)->get();
    }

    #[Computed]
    public function selectedOutlet(): ?Outlet
    {
        if (! $this->checkin_outlet_id) {
            return null;
        }

        return Outlet::select(['id', 'kode_outlet', 'nama_outlet', 'alamat_outlet', 'distric', 'latlong', 'radius'])
            ->find($this->checkin_outlet_id);
    }

    public function selectOutlet(int $outletId): void
    {
        $this->checkin_outlet_id = $outletId;
        unset($this->selectedOutlet);
    }

    public function clearOutlet(): void
    {
        $this->checkin_outlet_id = null;
        unset($this->selectedOutlet);
    }

    public function setLocation(string $latlong): void
    {
        $this->checkin_latlong = $latlong;
        $this->locationReady = true;
        $this->locationError = null;
    }

    public function setLocationError(string $error): void
    {
        $this->locationError = $error;
        $this->locationReady = false;
    }

    public function proceedToPhoto(): void
    {
        // 1. Validasi lokasi user
        if (! $this->locationReady || empty($this->checkin_latlong)) {
            Notification::make()
                ->title('Lokasi Diperlukan')
                ->body('Aktifkan GPS dan izinkan akses lokasi terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        // 2. Validasi outlet dipilih
        if (! $this->checkin_outlet_id) {
            Notification::make()
                ->title('Pilih Outlet')
                ->body('Silakan pilih outlet terlebih dahulu.')
                ->warning()
                ->send();

            return;
        }

        // Get fresh outlet data untuk validasi
        $outlet = Outlet::find($this->checkin_outlet_id);
        if (! $outlet) {
            Notification::make()
                ->title('Outlet Tidak Ditemukan')
                ->body('Outlet tidak ditemukan. Silakan pilih ulang.')
                ->danger()
                ->send();

            return;
        }

        // 3. Validasi koordinat outlet
        if (empty($outlet->latlong) || $outlet->latlong === '-') {
            Notification::make()
                ->title('Koordinat Tidak Valid')
                ->body('Outlet ini belum memiliki koordinat yang valid. Silakan update lokasi GPS outlet terlebih dahulu.')
                ->danger()
                ->persistent()
                ->actions([
                    \Filament\Actions\Action::make('update')
                        ->label('Update Outlet')
                        ->url(UpdateOutlet::getUrl(['outlet_id' => $outlet->id]))
                        ->button(),
                ])
                ->send();

            return;
        }

        // Parse dan validasi format latlong
        $parts = explode(',', $outlet->latlong);
        if (count($parts) !== 2) {
            Notification::make()
                ->title('Koordinat Tidak Valid')
                ->body('Format koordinat outlet tidak valid. Silakan update lokasi GPS outlet.')
                ->danger()
                ->persistent()
                ->actions([
                    \Filament\Actions\Action::make('update')
                        ->label('Update Outlet')
                        ->url(UpdateOutlet::getUrl(['outlet_id' => $outlet->id]))
                        ->button(),
                ])
                ->send();

            return;
        }

        // 4. Validasi foto outlet (harus punya foto lengkap atau video)
        $hasAllPhotos = ! empty($outlet->poto_depan)
            && ! empty($outlet->poto_kiri)
            && ! empty($outlet->poto_kanan)
            && ! empty($outlet->poto_shop_sign);
        $hasVideo = ! empty($outlet->video);

        if (! $hasAllPhotos && ! $hasVideo) {
            Notification::make()
                ->title('Foto Outlet Belum Lengkap')
                ->body('Outlet ini belum memiliki foto/video lengkap. Silakan upload foto outlet terlebih dahulu (Depan, Kiri, Kanan, Shop Sign) atau Video.')
                ->danger()
                ->persistent()
                ->actions([
                    \Filament\Actions\Action::make('update')
                        ->label('Update Foto')
                        ->url(UpdateOutlet::getUrl(['outlet_id' => $outlet->id]))
                        ->button(),
                ])
                ->send();

            return;
        }

        // 5. Validasi radius (jika outlet punya radius > 0)
        if ($outlet->radius > 0) {
            $distance = $this->calculateDistance($this->checkin_latlong, $outlet->latlong);

            if ($distance > $outlet->radius) {
                // Set data untuk modal
                $this->userDistance = $distance;
                $this->outletRadius = (float) $outlet->radius;
                $this->outletLatlong = $outlet->latlong;
                $this->showRadiusModal = true;

                return;
            }
        }

        // Semua validasi passed, lanjut ke photo
        $this->checkinStep = 'photo';
    }

    public function closeRadiusModal(): void
    {
        $this->showRadiusModal = false;
        $this->userDistance = null;
        $this->outletRadius = null;
        $this->outletLatlong = null;
    }

    public function navigateToOutlet(): void
    {
        // This will be handled by JavaScript
        $this->closeRadiusModal();
    }

    public function backToSelect(): void
    {
        $this->checkinStep = 'select';
        $this->checkin_photo = null;
    }

    public function updatedOutletSearch(): void
    {
        unset($this->extracallOutlets);
    }

    public function checkinForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                ToggleButtons::make('checkin_tipe')
                    ->label('Tipe Visit')
                    ->options([
                        'PLANNED' => 'PLANNED',
                        'EXTRACALL' => 'EXTRACALL',
                    ])
                    ->icons([
                        'PLANNED' => 'heroicon-o-calendar',
                        'EXTRACALL' => 'heroicon-o-bolt',
                    ])
                    ->colors([
                        'PLANNED' => 'primary',
                        'EXTRACALL' => 'info',
                    ])
                    ->inline()
                    ->required()
                    ->default('PLANNED')
                    ->live(),

                Select::make('checkin_outlet_id')
                    ->label('Pilih Outlet')
                    ->required()
                    ->searchable()
                    ->options(function ($get) {
                        if ($get('checkin_tipe') === 'PLANNED') {
                            return $this->plannedOutlets->mapWithKeys(fn ($outlet) => [
                                $outlet->id => "[{$outlet->kode_outlet}] {$outlet->nama_outlet}",
                            ]);
                        }

                        // Extracall: semua outlet sesuai scope
                        $user = Auth::user();

                        return Outlet::query()
                            ->accessibleTo($user)
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn ($outlet) => [
                                $outlet->id => "[{$outlet->kode_outlet}] {$outlet->nama_outlet}",
                            ]);
                    })
                    ->helperText(fn ($get) => $get('checkin_tipe') === 'PLANNED'
                        ? 'Outlet dari Plan Visit hari ini'
                        : 'Semua outlet yang dapat diakses'),

                Hidden::make('checkin_latlong'),

                FileUpload::make('checkin_photo')
                    ->label('Foto Check-in')
                    ->image()
                    ->required()
                    ->disk(StorageDisk::default())
                    ->directory('visits'),
            ])
            ->statePath('data');
    }

    public function checkoutForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('checkout_visit_id'),
                Hidden::make('checkout_latlong'),

                FileUpload::make('checkout_photo')
                    ->label('Foto Check-out')
                    ->image()
                    ->required()
                    ->disk(StorageDisk::default())
                    ->directory('visits'),

                ToggleButtons::make('checkout_transaksi')
                    ->label('Ada Transaksi?')
                    ->options([
                        'YES' => 'Ya',
                        'NO' => 'Tidak',
                    ])
                    ->icons([
                        'YES' => 'heroicon-o-check-circle',
                        'NO' => 'heroicon-o-x-circle',
                    ])
                    ->colors([
                        'YES' => 'success',
                        'NO' => 'danger',
                    ])
                    ->inline()
                    ->default('NO'),

                Textarea::make('checkout_laporan')
                    ->label('Laporan Visit')
                    ->rows(3),
            ])
            ->statePath('data');
    }

    public function checkin(): void
    {
        // Validasi ada visit aktif
        if ($this->activeVisit) {
            Notification::make()
                ->title('Gagal Check-in')
                ->body('Anda masih memiliki visit aktif. Silakan checkout terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        // Validasi outlet
        if (! $this->checkin_outlet_id) {
            Notification::make()
                ->title('Gagal Check-in')
                ->body('Silakan pilih outlet terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        $outlet = Outlet::find($this->checkin_outlet_id);
        if (! $outlet) {
            Notification::make()
                ->title('Gagal Check-in')
                ->body('Outlet tidak ditemukan.')
                ->danger()
                ->send();

            return;
        }

        // Validasi lokasi jika radius > 0
        if ($outlet->radius > 0 && $outlet->latlong) {
            if (! $this->checkin_latlong) {
                Notification::make()
                    ->title('Lokasi Diperlukan')
                    ->body('Aktifkan GPS dan izinkan akses lokasi untuk check-in.')
                    ->danger()
                    ->send();

                return;
            }

            $distance = $this->calculateDistance($this->checkin_latlong, $outlet->latlong);
            if ($distance > $outlet->radius) {
                Notification::make()
                    ->title('Diluar Jangkauan')
                    ->body("Anda berada {$distance}m dari outlet. Maksimal radius: {$outlet->radius}m")
                    ->danger()
                    ->send();

                return;
            }
        }

        // Validasi foto
        if (! $this->checkin_photo) {
            Notification::make()
                ->title('Foto Diperlukan')
                ->body('Silakan ambil foto untuk check-in.')
                ->danger()
                ->send();

            return;
        }

        // Store photo dengan FileUploadService (flat storage + optimized filename)
        $photoPath = null;
        if ($this->checkin_photo) {
            $photoPath = app(FileUploadService::class)->uploadImageOptimized(
                $this->checkin_photo,
                'visit-in'
            );
        }

        // Buat visit
        $visit = Visit::create([
            'user_id' => Auth::id(),
            'outlet_id' => $this->checkin_outlet_id,
            'tanggal_visit' => Carbon::today(),
            'tipe_visit' => $this->checkin_tipe,
            'latlong_in' => $this->checkin_latlong,
            'check_in_time' => Carbon::now(),
            'picture_visit_in' => $photoPath,
            'transaksi' => 'NO',
        ]);

        // Update PlanVisit jika PLANNED
        if ($this->checkin_tipe === 'PLANNED') {
            $today = Carbon::today();

            PlanVisit::query()
                ->where('user_id', Auth::id())
                ->where('outlet_id', $this->checkin_outlet_id)
                ->whereNull('realized_at')
                ->where(function ($q) use ($today) {
                    $q->where(function ($daily) use ($today) {
                        $daily->where('schedule_scope', 'daily')
                            ->whereDate('tanggal_visit', $today);
                    })->orWhere(function ($weekly) use ($today) {
                        $weekly->where('schedule_scope', 'weekly')
                            ->whereDate('period_start', '<=', $today)
                            ->whereDate('period_end', '>=', $today);
                    });
                })
                ->first()
                ?->markAsRealized($visit);
        }

        // Reset form
        $this->reset(['checkin_outlet_id', 'checkin_tipe', 'checkin_latlong', 'checkin_photo', 'outletSearch']);
        $this->checkin_tipe = 'PLANNED';
        $this->checkinStep = 'select';

        Notification::make()
            ->title('Check-in Berhasil')
            ->body("Visit ke {$outlet->nama_outlet} dimulai.")
            ->success()
            ->send();

        unset($this->todayVisits, $this->activeVisit, $this->plannedOutlets, $this->selectedOutlet, $this->extracallOutlets);
    }

    public function checkout(): void
    {
        $visit = $this->activeVisit;

        if (! $visit) {
            Notification::make()
                ->title('Gagal Checkout')
                ->body('Tidak ada visit aktif.')
                ->danger()
                ->send();

            return;
        }

        // Validasi foto
        if (! $this->checkout_photo) {
            Notification::make()
                ->title('Foto Diperlukan')
                ->body('Silakan ambil foto untuk checkout.')
                ->danger()
                ->send();

            return;
        }

        // Validasi laporan
        if (empty($this->checkout_laporan)) {
            Notification::make()
                ->title('Laporan Diperlukan')
                ->body('Silakan isi laporan kunjungan.')
                ->danger()
                ->send();

            return;
        }

        // Store photo dengan FileUploadService (flat storage + optimized filename)
        $photoPath = null;
        if ($this->checkout_photo) {
            $photoPath = app(FileUploadService::class)->uploadImageOptimized(
                $this->checkout_photo,
                'visit-out'
            );
        }

        $outletName = $visit->outlet?->nama_outlet;

        $visit->update([
            'latlong_out' => $this->checkout_latlong,
            'check_out_time' => Carbon::now(),
            'picture_visit_out' => $photoPath,
            'transaksi' => $this->checkout_transaksi,
            'laporan_visit' => $this->checkout_laporan,
        ]);

        // Reset form
        $this->reset(['checkout_latlong', 'checkout_photo', 'checkout_transaksi', 'checkout_laporan']);
        $this->checkout_transaksi = 'NO';

        Notification::make()
            ->title('Checkout Berhasil')
            ->body("Visit ke {$outletName} selesai.")
            ->success()
            ->send();

        unset($this->todayVisits, $this->activeVisit, $this->plannedOutlets, $this->selectedOutlet, $this->extracallOutlets);
    }

    protected function calculateDistance(string $from, string $to): float
    {
        [$lat1, $lon1] = array_map('floatval', explode(',', $from));
        [$lat2, $lon2] = array_map('floatval', explode(',', $to));

        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c);
    }

    protected function getForms(): array
    {
        return [
            'checkinForm',
            'checkoutForm',
        ];
    }
}
