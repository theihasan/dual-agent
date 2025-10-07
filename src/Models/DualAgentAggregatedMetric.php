<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $metric_type
 * @property string $event_type
 * @property Carbon $metric_date
 * @property int|null $metric_hour
 * @property int $total_events
 * @property int|null $unique_users
 * @property int|null $unique_sessions
 * @property float|null $avg_duration
 * @property float|null $min_duration
 * @property float|null $max_duration
 * @property float|null $p95_duration
 * @property float|null $p99_duration
 * @property int|null $status_2xx
 * @property int|null $status_3xx
 * @property int|null $status_4xx
 * @property int|null $status_5xx
 * @property int|null $total_queries
 * @property float|null $avg_query_duration
 * @property int|null $total_exceptions
 * @property array|null $top_exceptions
 * @property int|null $jobs_queued
 * @property int|null $jobs_completed
 * @property int|null $jobs_failed
 * @property int|null $avg_memory_usage
 * @property int|null $peak_memory_usage
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DualAgentAggregatedMetric extends Model
{
    protected $fillable = [
        'metric_type',
        'event_type',
        'metric_date',
        'metric_hour',

        // Count metrics
        'total_events',
        'unique_users',
        'unique_sessions',

        // Performance metrics
        'avg_duration',
        'min_duration',
        'max_duration',
        'p95_duration',
        'p99_duration',

        // HTTP status codes
        'status_2xx',
        'status_3xx',
        'status_4xx',
        'status_5xx',

        // Database metrics
        'total_queries',
        'avg_query_duration',

        // Exception metrics
        'total_exceptions',
        'top_exceptions',

        // Job metrics
        'jobs_queued',
        'jobs_completed',
        'jobs_failed',

        // Memory usage
        'avg_memory_usage',
        'peak_memory_usage',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'top_exceptions' => 'array',
        'avg_duration' => 'decimal:3',
        'min_duration' => 'decimal:3',
        'max_duration' => 'decimal:3',
        'p95_duration' => 'decimal:3',
        'p99_duration' => 'decimal:3',
        'avg_query_duration' => 'decimal:3',
    ];

    // Scopes for metric types
    public function scopeHourly(Builder $query): Builder
    {
        return $query->where('metric_type', 'hourly');
    }

    public function scopeDaily(Builder $query): Builder
    {
        return $query->where('metric_type', 'daily');
    }

    public function scopeWeekly(Builder $query): Builder
    {
        return $query->where('metric_type', 'weekly');
    }

    // Scopes for event types
    public function scopeRequests(Builder $query): Builder
    {
        return $query->where('event_type', 'request');
    }

    public function scopeQueries(Builder $query): Builder
    {
        return $query->where('event_type', 'query');
    }

    public function scopeExceptions(Builder $query): Builder
    {
        return $query->where('event_type', 'exception');
    }

    public function scopeJobs(Builder $query): Builder
    {
        return $query->where('event_type', 'job');
    }

    // Time-based scopes
    public function scopeToday(Builder $query): Builder
    {
        return $query->where('metric_date', today());
    }

    public function scopeYesterday(Builder $query): Builder
    {
        return $query->where('metric_date', today()->subDay());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('metric_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('metric_date', now()->month)
                    ->whereYear('metric_date', now()->year);
    }

    public function scopeBetweenDates(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('metric_date', [$start, $end]);
    }

    // Helper methods
    public function getTotalRequests(): int
    {
        if ($this->event_type === 'request') {
            return (int) $this->total_events;
        }

        return 0;
    }

    public function getErrorRate(): float
    {
        if ($this->event_type !== 'request' || $this->total_events === 0) {
            return 0.0;
        }

        $totalErrors = $this->status_4xx + $this->status_5xx;

        return round(($totalErrors / $this->total_events) * 100, 2);
    }

    public function getJobSuccessRate(): float
    {
        if ($this->event_type !== 'job') {
            return 0.0;
        }

        $totalJobs = $this->jobs_completed + $this->jobs_failed;

        if ($totalJobs === 0) {
            return 100.0;
        }

        return round(($this->jobs_completed / $totalJobs) * 100, 2);
    }

    public function getAverageResponseTime(): float
    {
        return (float) $this->avg_duration;
    }

    public function isHourly(): bool
    {
        return $this->metric_type === 'hourly';
    }

    public function isDaily(): bool
    {
        return $this->metric_type === 'daily';
    }

    public function isWeekly(): bool
    {
        return $this->metric_type === 'weekly';
    }
}
