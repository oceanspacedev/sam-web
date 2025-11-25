<?php

namespace App\Models;

use App\Traits\CleansUpMedia;
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
}
