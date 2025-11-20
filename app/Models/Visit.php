<?php

namespace App\Models;

use App\Traits\CleansUpMedia;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use CleansUpMedia;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    protected array $mediaCleanupFields = [
        'picture_visit_in',
        'picture_visit_out',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class)->withTrashed();
    }

    public function formatForAPI(): array
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
