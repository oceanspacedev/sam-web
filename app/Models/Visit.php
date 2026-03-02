<?php

namespace App\Models;

use App\Traits\CleansUpMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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

    protected function casts(): array
    {
        return [
            'tanggal_visit' => 'date',
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function visitable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function isOutletVisit(): bool
    {
        return $this->visitable_type === Outlet::class;
    }

    public function isRegisterVisit(): bool
    {
        return $this->visitable_type === Register::class;
    }

    /**
     * Backward-compat accessor: returns outlet_id if visitable is Outlet, null otherwise.
     */
    public function getOutletIdAttribute(): ?int
    {
        return $this->isOutletVisit() ? $this->visitable_id : null;
    }

    /**
     * Backward-compat accessor: returns register_id if visitable is Register, null otherwise.
     */
    public function getRegisterIdAttribute(): ?int
    {
        return $this->isRegisterVisit() ? $this->visitable_id : null;
    }

    /**
     * Backward-compat: load outlet relation via visitable when it's an Outlet.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'visitable_id')
            ->withTrashed();
    }
}
