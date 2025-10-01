<?php

namespace App\Models;

use App\Support\StorageDisk;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Visit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class)->withTrashed();
    }

    protected static function booted()
    {
        static::saving(function ($visit) {
            $visit->calculateDurasiVisit();
        });

        static::updating(function ($model) {
            $disk = StorageDisk::default();
            /** @var FilesystemAdapter $storage */
            $storage = Storage::disk($disk);

            // Jika field picture_visit_in berubah, hapus gambar lama
            if ($model->isDirty('picture_visit_in') && $model->getOriginal('picture_visit_in')) {
                $oldFile = $model->getOriginal('picture_visit_in');
                if ($storage->exists($oldFile)) {
                    $storage->delete($oldFile);
                }
            }

            // Jika field picture_visit_out berubah, hapus gambar lama
            if ($model->isDirty('picture_visit_out') && $model->getOriginal('picture_visit_out')) {
                $oldFileOut = $model->getOriginal('picture_visit_out');
                if ($storage->exists($oldFileOut)) {
                    $storage->delete($oldFileOut);
                }
            }
        });
    }

    protected function calculateDurasiVisit(): void
    {
        if (! empty($this->check_in_time) && ! empty($this->check_out_time)) {
            try {
                $checkIn = Carbon::parse($this->check_in_time);
                $checkOut = Carbon::parse($this->check_out_time);

                $durationInMinutes = $checkIn->diffInMinutes($checkOut);

                $this->durasi_visit = $durationInMinutes;
            } catch (\Exception $e) {
                $this->durasi_visit = null;
            }
        } else {
            $this->durasi_visit = null;
        }
    }

    public function formatForAPI()
    {
        return [
            'id' => $this->id,
            'tanggal_visit' => Carbon::parse($this->tanggal_visit)->getPreciseTimestamp(3),
            'user_id' => $this->user_id,
            'outlet_id' => $this->outlet_id,
            'tipe_visit' => $this->tipe_visit,
            'latlong_in' => $this->latlong_in,
            'latlong_out' => $this->latlong_out,
            'check_in_time' => Carbon::parse($this->check_in_time)->getPreciseTimestamp(3),
            'check_out_time' => $this->check_out_time ? Carbon::parse($this->check_out_time)->getPreciseTimestamp(3) : null,
            'laporan_visit' => $this->laporan_visit,
            'durasi_visit' => $this->durasi_visit,
            'picture_visit_in' => $this->picture_visit_in,
            'picture_visit_out' => $this->picture_visit_out,
            'outlet' => $this->outlet,
            'user' => $this->user,
            'transaksi' => $this->transaksi,
        ];
    }
}
