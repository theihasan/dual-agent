<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DualAgentMetric extends Model
{
    protected $fillable = [
        'event_type',
        'event_timestamp',
        'trace_id',
        'session_id',
        'user_id',
        
        // Request metrics
        'method',
        'url',
        'route_name',
        'route_path',
        'status_code',
        'duration',
        'memory_usage',
        'request_size',
        'response_size',
        
        // Database metrics
        'sql',
        'connection',
        'query_duration',
        'bindings',
        
        // Exception metrics
        'exception_class',
        'exception_message',
        'exception_file',
        'exception_line',
        'exception_trace',
        
        // Job metrics
        'job_class',
        'queue',
        'job_status',
        'attempts',
        'job_duration',
        
        // Cache metrics
        'cache_key',
        'cache_operation',
        'cache_store',
        
        // Mail metrics
        'mail_class',
        'mail_to',
        'mail_subject',
        
        // Log metrics
        'log_level',
        'log_message',
        'log_context',
        
        // Performance stages
        'bootstrap_duration',
        'before_middleware_duration',
        'action_duration',
        'render_duration',
        'after_middleware_duration',
        'terminating_duration',
        
        // Environment info
        'environment',
        'server_name',
        'app_version',
        
        // Additional metadata
        'custom_metadata',
        'raw_payload',
    ];

    protected $casts = [
        'event_timestamp' => 'datetime',
        'bindings' => 'array',
        'exception_trace' => 'array',
        'mail_to' => 'array',
        'log_context' => 'array',
        'custom_metadata' => 'array',
        'raw_payload' => 'array',
        'duration' => 'decimal:3',
        'query_duration' => 'decimal:3',
        'job_duration' => 'decimal:3',
        'bootstrap_duration' => 'decimal:3',
        'before_middleware_duration' => 'decimal:3',
        'action_duration' => 'decimal:3',
        'render_duration' => 'decimal:3',
        'after_middleware_duration' => 'decimal:3',
        'terminating_duration' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    // Scopes for different event types
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

    public function scopeCache(Builder $query): Builder
    {
        return $query->where('event_type', 'cache');
    }

    public function scopeMail(Builder $query): Builder
    {
        return $query->where('event_type', 'mail');
    }

    public function scopeLogs(Builder $query): Builder
    {
        return $query->where('event_type', 'log');
    }

    // Time-based scopes
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('event_timestamp', today());
    }

    public function scopeYesterday(Builder $query): Builder
    {
        return $query->whereDate('event_timestamp', today()->subDay());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('event_timestamp', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('event_timestamp', now()->month)
                    ->whereYear('event_timestamp', now()->year);
    }

    public function scopeBetweenDates(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('event_timestamp', [$start, $end]);
    }

    // Performance scopes
    public function scopeSlowRequests(Builder $query, float $threshold = 1000.0): Builder
    {
        return $query->where('event_type', 'request')
                    ->where('duration', '>', $threshold);
    }

    public function scopeSlowQueries(Builder $query, float $threshold = 100.0): Builder
    {
        return $query->where('event_type', 'query')
                    ->where('query_duration', '>', $threshold);
    }

    public function scopeErrors(Builder $query): Builder
    {
        return $query->where('event_type', 'request')
                    ->where('status_code', '>=', 400);
    }

    public function scopeFailedJobs(Builder $query): Builder
    {
        return $query->where('event_type', 'job')
                    ->where('job_status', 'failed');
    }

    // Helper methods
    public function isRequest(): bool
    {
        return $this->event_type === 'request';
    }

    public function isQuery(): bool
    {
        return $this->event_type === 'query';
    }

    public function isException(): bool
    {
        return $this->event_type === 'exception';
    }

    public function isJob(): bool
    {
        return $this->event_type === 'job';
    }

    public function isError(): bool
    {
        return $this->isRequest() && $this->status_code >= 400;
    }

    public function isSlow(float $threshold = 1000.0): bool
    {
        return $this->duration && $this->duration > $threshold;
    }

    public function getTotalDuration(): float
    {
        if ($this->isRequest()) {
            return (float) $this->duration;
        }

        if ($this->isQuery()) {
            return (float) $this->query_duration;
        }

        if ($this->isJob()) {
            return (float) $this->job_duration;
        }

        return 0.0;
    }
}