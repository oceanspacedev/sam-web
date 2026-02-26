<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    use HasFactory;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_BADANUSAHA = 'badanusaha';

    public const SCOPE_DIVISION = 'division';

    public const SCOPE_REGION = 'region';

    public const SCOPE_CLUSTER = 'cluster';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'allow_register_visit' => 'boolean',
        'default_register_radius' => 'integer',
        'plan_visit_min_days' => 'integer',
    ];

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class)->withTrashed();
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id')->withTrashed();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)->withTrashed();
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class)->withTrashed();
    }
}
