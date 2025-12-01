<?php

namespace App\Filament\Pages;

use App\Models\Outlet;
use App\Support\StorageDisk;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

class UpdateOutlet extends Page
{
    use HasPageShield;
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Update Outlet';

    protected static ?string $title = 'Update Outlet';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.update-outlet';

    // Outlet data
    public ?int $outlet_id = null;

    public ?string $outlet_name = null;

    public ?string $outlet_code = null;

    // Step: 'location' or 'form'
    public string $step = 'location';

    // Location
    public ?string $current_latlong = null;

    public bool $locationConfirmed = false;

    public bool $locationReady = false;

    public ?string $locationError = null;

    // Form fields
    public ?string $nama_pemilik = null;

    public ?string $nomor_pemilik = null;

    // Photos
    public $photo_shop_sign = null;

    public $photo_depan = null;

    public $photo_kanan = null;

    public $photo_kiri = null;

    public $video = null;

    // Existing photos (from DB)
    public ?string $existing_shop_sign = null;

    public ?string $existing_depan = null;

    public ?string $existing_kanan = null;

    public ?string $existing_kiri = null;

    public ?string $existing_video = null;

    public function mount(): void
    {
        $outletId = request()->query('outlet_id');

        if ($outletId) {
            $this->loadOutlet((int) $outletId);
        }
    }

    public function loadOutlet(int $outletId): void
    {
        $outlet = Outlet::find($outletId);

        if (! $outlet) {
            Notification::make()
                ->title('Outlet Tidak Ditemukan')
                ->danger()
                ->send();

            return;
        }

        $this->outlet_id = $outlet->id;
        $this->outlet_name = $outlet->nama_outlet;
        $this->outlet_code = $outlet->kode_outlet;
        $this->nama_pemilik = $outlet->nama_pemilik_outlet;
        $this->nomor_pemilik = $outlet->nomor_pemilik_outlet;

        // Load existing photos
        $this->existing_shop_sign = $outlet->poto_shop_sign;
        $this->existing_depan = $outlet->poto_depan;
        $this->existing_kanan = $outlet->poto_kanan;
        $this->existing_kiri = $outlet->poto_kiri;
        $this->existing_video = $outlet->video;
    }

    public function setLocation(string $latlong): void
    {
        $this->current_latlong = $latlong;
        $this->locationReady = true;
        $this->locationError = null;
    }

    public function setLocationError(string $error): void
    {
        $this->locationError = $error;
        $this->locationReady = false;
    }

    public function confirmLocation(): void
    {
        if (empty($this->current_latlong)) {
            Notification::make()
                ->title('Lokasi Diperlukan')
                ->body('Tunggu hingga lokasi terdeteksi.')
                ->danger()
                ->send();

            return;
        }

        $this->locationConfirmed = true;
        $this->step = 'form';
    }

    public function backToLocation(): void
    {
        $this->step = 'location';
        $this->locationConfirmed = false;
    }

    public function removePhoto(string $field): void
    {
        $this->{$field} = null;
    }

    public function removeExistingPhoto(string $field): void
    {
        $this->{'existing_'.$field} = null;
    }

    public function submit(): void
    {
        if (! $this->outlet_id) {
            Notification::make()
                ->title('Error')
                ->body('Outlet tidak ditemukan.')
                ->danger()
                ->send();

            return;
        }

        $outlet = Outlet::find($this->outlet_id);
        if (! $outlet) {
            Notification::make()
                ->title('Error')
                ->body('Outlet tidak ditemukan.')
                ->danger()
                ->send();

            return;
        }

        // Prepare update data
        $data = [
            'nama_pemilik_outlet' => $this->nama_pemilik,
            'nomor_pemilik_outlet' => $this->nomor_pemilik,
            'latlong' => $this->current_latlong,
        ];

        // Handle photo uploads
        $disk = StorageDisk::default();

        if ($this->photo_shop_sign) {
            $data['poto_shop_sign'] = $this->photo_shop_sign->store('outlets', $disk);
        } elseif ($this->existing_shop_sign === null && $outlet->poto_shop_sign) {
            $data['poto_shop_sign'] = null;
        }

        if ($this->photo_depan) {
            $data['poto_depan'] = $this->photo_depan->store('outlets', $disk);
        } elseif ($this->existing_depan === null && $outlet->poto_depan) {
            $data['poto_depan'] = null;
        }

        if ($this->photo_kanan) {
            $data['poto_kanan'] = $this->photo_kanan->store('outlets', $disk);
        } elseif ($this->existing_kanan === null && $outlet->poto_kanan) {
            $data['poto_kanan'] = null;
        }

        if ($this->photo_kiri) {
            $data['poto_kiri'] = $this->photo_kiri->store('outlets', $disk);
        } elseif ($this->existing_kiri === null && $outlet->poto_kiri) {
            $data['poto_kiri'] = null;
        }

        if ($this->video) {
            $data['video'] = $this->video->store('outlets/videos', $disk);
        } elseif ($this->existing_video === null && $outlet->video) {
            $data['video'] = null;
        }

        $outlet->update($data);

        Notification::make()
            ->title('Berhasil')
            ->body('Data outlet berhasil diperbarui.')
            ->success()
            ->send();

        // Redirect back to LiveVisit
        $this->redirect(LiveVisit::getUrl());
    }

    public function hasAllPhotos(): bool
    {
        $hasShopSign = $this->photo_shop_sign || $this->existing_shop_sign;
        $hasDepan = $this->photo_depan || $this->existing_depan;
        $hasKanan = $this->photo_kanan || $this->existing_kanan;
        $hasKiri = $this->photo_kiri || $this->existing_kiri;
        $hasVideo = $this->video || $this->existing_video;

        return ($hasShopSign && $hasDepan && $hasKanan && $hasKiri) || $hasVideo;
    }
}
