<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletArchive extends Model
{
    use HasFactory;

    protected $table = 'outlets_archives';

    protected $guarded = ['id'];

    protected $casts = [
        'archived_at' => 'datetime',
        'original_created_at' => 'datetime',
        'original_updated_at' => 'datetime',
        'original_deleted_at' => 'datetime',
    ];

    /**
     * Get the original outlet (if still exists)
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class);
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }
}
