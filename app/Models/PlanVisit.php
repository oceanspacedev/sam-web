<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanVisit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'realized_at' => 'datetime',
    ];

    public function scopeFilter(Builder $query, ?string $term = null): Builder
    {
        $term ??= request('search');

        return $query->when($term, function (Builder $query, string $search): void {
            $query->where('nama_lengkap', 'like', "%{$search}%");
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class)->withTrashed();
    }

    public function realizedVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'realized_visit_id')->withTrashed();
    }

    public function scopeUnrealized(Builder $query): Builder
    {
        return $query->whereNull('realized_at');
    }

    public function markAsRealized(?Visit $visit = null, ?Carbon $timestamp = null): void
    {
        $this->forceFill([
            'realized_at' => $timestamp ?? now(),
            'realized_visit_id' => $visit?->id,
        ])->save();
    }

    public function clearRealization(): void
    {
        $this->forceFill([
            'realized_at' => null,
            'realized_visit_id' => null,
        ])->save();
    }

    public static function schedulePayload(Carbon|string $startDate, string $scope = 'daily', Carbon|string|null $endDate = null): array
    {
        $start = $startDate instanceof Carbon ? $startDate->copy() : Carbon::parse($startDate);
        $start->startOfDay();

        if ($scope === 'weekly') {
            // Weekly is Monday to Saturday (6 days)
            $start->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->addDays(5); // Monday + 5 days = Saturday
        } else {
            $end = $endDate ? ($endDate instanceof Carbon ? $endDate->copy() : Carbon::parse($endDate)) : $start->copy();
            $end->startOfDay();
        }

        return [
            'schedule_scope' => $scope,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'schedule_week' => $start->weekOfYear,
            'schedule_year' => $start->year,
            'tanggal_visit' => $start,
        ];
    }

    public function isWeekly(): bool
    {
        return $this->schedule_scope === 'weekly';
    }

    public function visitCoversDate(Carbon|string $date): bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        if ($this->isWeekly() && $this->period_start && $this->period_end) {
            return $date->between($this->period_start, $this->period_end);
        }

        return $this->tanggal_visit ? $date->isSameDay(Carbon::parse($this->tanggal_visit)) : false;
    }

    public function formatForAPI(): array
    {
        $periodStart = $this->period_start ? Carbon::parse($this->period_start) : null;
        $periodEnd = $this->period_end ? Carbon::parse($this->period_end) : null;

        return [
            'id' => $this->id,
            'tanggal_visit' => $this->tanggal_visit ? Carbon::parse($this->tanggal_visit)->getPreciseTimestamp(3) : null,
            'user_id' => $this->user_id,
            'outlet_id' => $this->outlet_id,
            'schedule_scope' => $this->schedule_scope,
            'period_start' => $periodStart?->getPreciseTimestamp(3),
            'period_end' => $periodEnd?->getPreciseTimestamp(3),
            'schedule_week' => $this->schedule_week,
            'schedule_year' => $this->schedule_year,
            'realized_at' => $this->realized_at ? Carbon::parse($this->realized_at)->getPreciseTimestamp(3) : null,
            'realized_visit_id' => $this->realized_visit_id,
            'is_realized' => (bool) $this->realized_at,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->getPreciseTimestamp(3) : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->getPreciseTimestamp(3) : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->getPreciseTimestamp(3) : null,
            // Include loaded relations minimally to be consistent with other APIs
            'user' => $this->relationLoaded('user') ? $this->user : null,
            'outlet' => $this->relationLoaded('outlet') ? $this->outlet : null,
            'realized_visit' => $this->relationLoaded('realizedVisit') ? $this->realizedVisit : null,
        ];
    }
}
