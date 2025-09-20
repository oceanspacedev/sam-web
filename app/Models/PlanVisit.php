<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanVisit extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    public function scopeFilter($query)
    {
        if (request('search')) {
            $query->where('nama_lengkap', 'like', '%'.request('search').'%');
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function formatForAPI()
    {
        return [
            'id' => $this->id,
            'tanggal_visit' => $this->tanggal_visit ? Carbon::parse($this->tanggal_visit)->getPreciseTimestamp(3) : null,
            'user_id' => $this->user_id,
            'outlet_id' => $this->outlet_id,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->getPreciseTimestamp(3) : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->getPreciseTimestamp(3) : null,
            // Include loaded relations minimally to be consistent with other APIs
            'user' => $this->relationLoaded('user') ? $this->user : null,
            'outlet' => $this->relationLoaded('outlet') ? $this->outlet : null,
        ];
    }
}
