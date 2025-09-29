<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Ingest;

use Ihasan\DualAgent\Contracts\CustomIngestContract;
use Ihasan\DualAgent\Models\DualAgentMetric;
use Ihasan\DualAgent\Support\DataTransformer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DatabaseIngest implements CustomIngestContract
{
    protected array $buffer = [];
    protected bool $shouldDigest = true;
    protected DataTransformer $transformer;
    protected string $cacheKey = 'dual_agent_buffer';

    public function __construct(
        protected bool $enabled = true,
        protected int $bufferSize = 100
    ) {
        $this->transformer = new DataTransformer();
        
        // Load existing buffer from cache for persistent buffering across requests
        $this->buffer = cache()->get($this->cacheKey, []);
        
        // Debug logging
        Log::info('[DualAgent] DatabaseIngest created', [
            'enabled' => $this->enabled,
            'bufferSize' => $this->bufferSize,
            'existing_buffer_count' => count($this->buffer),
        ]);
    }

    public function write(array $record): void
    {
        // Debug logging
        Log::info('[DualAgent] DatabaseIngest::write called', [
            'enabled' => $this->enabled,
            'record_type' => $record['t'] ?? 'unknown',
            'should_send' => $this->transformer->shouldSendRecord($record),
        ]);

        if (!$this->enabled || !$this->transformer->shouldSendRecord($record)) {
            Log::info('[DualAgent] Skipping record', [
                'enabled' => $this->enabled,
                'should_send' => $this->transformer->shouldSendRecord($record),
            ]);
            return;
        }

        try {
            $this->buffer[] = $record;
            
            // Persist buffer to cache for cross-request persistence
            cache()->put($this->cacheKey, $this->buffer, 3600); // 1 hour expiry
            
            Log::info('[DualAgent] Record added to buffer', [
                'buffer_count' => count($this->buffer),
                'buffer_size' => $this->bufferSize,
                'using_cache' => true,
            ]);

            if (count($this->buffer) >= $this->bufferSize) {
                Log::info('[DualAgent] Buffer full, digesting');
                $this->digest();
            }
        } catch (\Exception $e) {
            Log::error('[DualAgent] Failed to write record to buffer', [
                'error' => $e->getMessage(),
                'record_type' => $record['t'] ?? 'unknown',
            ]);
        }
    }

    public function writeNow(array $record): void
    {
        Log::info('[DualAgent] DatabaseIngest::writeNow called', [
            'record_type' => $record['t'] ?? 'unknown',
        ]);

        if (!$this->enabled || !$this->transformer->shouldSendRecord($record)) {
            return;
        }

        try {
            $this->saveToDatabase([$record]);
        } catch (\Exception $e) {
            Log::error('[DualAgent] Failed to write record immediately', [
                'error' => $e->getMessage(),
                'record_type' => $record['t'] ?? 'unknown',
            ]);
        }
    }

    public function ping(): void
    {
        Log::info('[DualAgent] DatabaseIngest::ping called');
        
        // For database storage, ping just checks if the database is accessible
        if (!$this->enabled) {
            return;
        }

        try {
            DB::connection()->getPdo();
            Log::info('[DualAgent] Database ping successful');
        } catch (\Exception $e) {
            Log::error('[DualAgent] Database ping failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function shouldDigest(bool $bool): void
    {
        Log::info('[DualAgent] shouldDigest called', ['value' => $bool]);
        $this->shouldDigest = $bool;
    }

    public function shouldDigestWhenBufferIsFull(bool $bool = true): void
    {
        Log::info('[DualAgent] shouldDigestWhenBufferIsFull called', ['value' => $bool]);
        $this->shouldDigest = $bool;
    }

    public function digest(): void
    {
        Log::info('[DualAgent] DatabaseIngest::digest called', [
            'enabled' => $this->enabled,
            'should_digest' => $this->shouldDigest,
            'buffer_count' => count($this->buffer),
        ]);

        if (!$this->enabled || !$this->shouldDigest || empty($this->buffer)) {
            Log::info('[DualAgent] Skipping digest', [
                'enabled' => $this->enabled,
                'should_digest' => $this->shouldDigest,
                'buffer_empty' => empty($this->buffer),
            ]);
            return;
        }

        try {
            $records = $this->buffer;
            $this->buffer = [];
            
            // Clear cached buffer
            cache()->forget($this->cacheKey);
            
            Log::info('[DualAgent] About to save to database', ['count' => count($records)]);
            $this->saveToDatabase($records);
        } catch (\Exception $e) {
            Log::error('[DualAgent] Failed to digest buffer', [
                'error' => $e->getMessage(),
                'buffer_size' => count($records ?? []),
            ]);
        }
    }

    public function flush(): void
    {
        Log::info('[DualAgent] DatabaseIngest::flush called');
        $this->buffer = [];
        
        // Clear cached buffer
        cache()->forget($this->cacheKey);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    public function getBufferCount(): int
    {
        return count($this->buffer);
    }

    protected function saveToDatabase(array $records): void
    {
        if (empty($records)) {
            Log::warning('[DualAgent] saveToDatabase called with empty records');
            return;
        }

        Log::info('[DualAgent] saveToDatabase called', ['count' => count($records)]);

        // Instead of batch insert, save records individually to handle different structures
        $successCount = 0;
        
        foreach ($records as $record) {
            try {
                $metricData = $this->transformRecordToMetric($record);
                if ($metricData) {
                    DualAgentMetric::create($metricData);
                    $successCount++;
                    
                    Log::debug('[DualAgent] Record saved successfully', [
                        'event_type' => $metricData['event_type'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('[DualAgent] Failed to save individual record', [
                    'error' => $e->getMessage(),
                    'event_type' => $record['t'] ?? 'unknown',
                ]);
            }
        }
        
        Log::info('[DualAgent] ✅ Metrics saved to database successfully!', [
            'total_records' => count($records),
            'successful_saves' => $successCount,
        ]);
    }

    protected function transformRecordToMetric(array $record): ?array
    {
        try {
            $eventType = $record['t'] ?? 'unknown';
            $timestamp = $record['timestamp'] ?? microtime(true);
            
            Log::debug('[DualAgent] Transforming record', [
                'event_type' => $eventType,
                'timestamp' => $timestamp,
                'has_trace' => isset($record['trace']),
            ]);
            
            // Base metric data - only include columns that exist for all record types
            $metricData = [
                'event_type' => $eventType,
                'event_timestamp' => date('Y-m-d H:i:s', (int) $timestamp),
                'trace_id' => $record['trace'] ?? $record['trace_id'] ?? null,
                'session_id' => session()->getId(),
                'user_id' => Auth::id(),
                'environment' => app()->environment(),
                'server_name' => gethostname() ?: 'unknown',
                'app_version' => config('app.version', '1.0.0'),
                'raw_payload' => $record,
            ];

            // Transform based on event type - only add relevant columns
            switch ($eventType) {
                case 'request':
                    $metricData = array_merge($metricData, [
                        'method' => $record['method'] ?? null,
                        'url' => $record['url'] ?? null,
                        'route_name' => $record['route_name'] ?? null,
                        'route_path' => $record['route_path'] ?? null,
                        'status_code' => $record['status_code'] ?? null,
                        'duration' => $record['duration'] ?? null,
                        'memory_usage' => $record['memory_usage'] ?? null,
                        'request_size' => $record['request_size'] ?? null,
                        'response_size' => $record['response_size'] ?? null,
                        'bootstrap_duration' => $record['bootstrap'] ?? null,
                        'before_middleware_duration' => $record['before_middleware'] ?? null,
                        'action_duration' => $record['action'] ?? null,
                        'render_duration' => $record['render'] ?? null,
                        'after_middleware_duration' => $record['after_middleware'] ?? null,
                        'terminating_duration' => $record['terminating'] ?? null,
                    ]);
                    break;
                    
                case 'query':
                    $metricData = array_merge($metricData, [
                        'sql' => $record['sql'] ?? null,
                        'connection' => $record['connection'] ?? null,
                        'query_duration' => $record['duration'] ?? $record['time'] ?? null,
                        'bindings' => $record['bindings'] ?? null,
                    ]);
                    break;
                    
                case 'exception':
                    $metricData = array_merge($metricData, [
                        'exception_class' => $record['class'] ?? $record['exception_class'] ?? null,
                        'exception_message' => $record['message'] ?? $record['exception_message'] ?? null,
                        'exception_file' => $record['file'] ?? null,
                        'exception_line' => $record['line'] ?? null,
                        'exception_trace' => $record['trace'] ?? $record['stack_trace'] ?? null,
                    ]);
                    break;
                    
                case 'job':
                case 'queued_job':
                    $metricData = array_merge($metricData, [
                        'job_class' => $record['job'] ?? $record['job_class'] ?? null,
                        'queue' => $record['queue'] ?? null,
                        'job_status' => $record['status'] ?? 'queued',
                        'attempts' => $record['attempts'] ?? null,
                        'job_duration' => $record['duration'] ?? null,
                    ]);
                    break;
                    
                case 'cache':
                    $metricData = array_merge($metricData, [
                        'cache_key' => $record['key'] ?? null,
                        'cache_operation' => $record['operation'] ?? $record['type'] ?? null,
                        'cache_store' => $record['store'] ?? null,
                    ]);
                    break;
                    
                case 'mail':
                    $metricData = array_merge($metricData, [
                        'mail_class' => $record['mailable'] ?? $record['mail_class'] ?? null,
                        'mail_to' => $record['to'] ?? null,
                        'mail_subject' => $record['subject'] ?? null,
                    ]);
                    break;
                    
                case 'log':
                    $metricData = array_merge($metricData, [
                        'log_level' => $record['level'] ?? null,
                        'log_message' => $record['message'] ?? null,
                        'log_context' => $record['context'] ?? null,
                    ]);
                    break;
                    
                default:
                    // Handle custom event types
                    $metricData['custom_metadata'] = array_diff_key($record, array_flip([
                        't', 'timestamp', 'trace', 'trace_id'
                    ]));
            }

            return $metricData;
            
        } catch (\Exception $e) {
            Log::warning('[DualAgent] Failed to transform record to metric', [
                'error' => $e->getMessage(),
                'record_type' => $record['t'] ?? 'unknown',
            ]);
            
            return null;
        }
    }
}