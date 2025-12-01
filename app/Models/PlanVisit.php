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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'realized_at' => 'datetime',
        ];
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
}
