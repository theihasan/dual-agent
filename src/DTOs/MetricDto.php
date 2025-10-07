<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\DTOs;

use JsonSerializable;

final readonly class MetricDto implements JsonSerializable
{
    public function __construct(
        public string $eventType,
        public float $timestamp,
        public string $traceId,
        public int|string|null $userId = null,
        public ?string $sessionId = null,
        public ?float $duration = null,
        public ?int $statusCode = null,
        public ?string $method = null,
        public ?string $url = null,
        public ?string $sql = null,
        public ?string $connection = null,
        public ?string $exceptionClass = null,
        public ?string $exceptionMessage = null,
        public ?string $file = null,
        public ?int $line = null,
        public ?string $jobClass = null,
        public ?string $queue = null,
        public ?string $status = null,
        public array $customMetadata = [],
        public array $rawData = []
    ) {}

    public static function fromNightwatchRecord(array $record): self
    {
        $eventType = $record['t'] ?? 'unknown';

        return new self(
            eventType: $eventType,
            timestamp: $record['timestamp'] ?? microtime(true),
            traceId: $record['trace_id'] ?? $record['trace'] ?? uniqid(),
            userId: auth()->id(),
            sessionId: session()->getId(),
            duration: $record['duration'] ?? null,
            statusCode: $record['status_code'] ?? null,
            method: $record['method'] ?? null,
            url: $record['url'] ?? null,
            sql: $record['sql'] ?? null,
            connection: $record['connection'] ?? null,
            exceptionClass: $record['exception_class'] ?? $record['class'] ?? null,
            exceptionMessage: $record['exception_message'] ?? $record['message'] ?? null,
            file: $record['file'] ?? null,
            line: $record['line'] ?? null,
            jobClass: $record['job_class'] ?? $record['job'] ?? null,
            queue: $record['queue'] ?? null,
            status: $record['status'] ?? null,
            customMetadata: $record['custom_metadata'] ?? [],
            rawData: $record
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'event_type' => $this->eventType,
            'timestamp' => $this->timestamp,
            'trace_id' => $this->traceId,
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'duration' => $this->duration,
            'status_code' => $this->statusCode,
            'method' => $this->method,
            'url' => $this->url,
            'sql' => $this->sql,
            'connection' => $this->connection,
            'exception_class' => $this->exceptionClass,
            'exception_message' => $this->exceptionMessage,
            'file' => $this->file,
            'line' => $this->line,
            'job_class' => $this->jobClass,
            'queue' => $this->queue,
            'status' => $this->status,
            'custom_metadata' => $this->customMetadata ?: null,
            'raw_data' => $this->rawData ?: null,
        ], fn($value) => $value !== null);
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function isRequest(): bool
    {
        return $this->eventType === 'request';
    }

    public function isQuery(): bool
    {
        return $this->eventType === 'query';
    }

    public function isException(): bool
    {
        return $this->eventType === 'exception';
    }

    public function isJob(): bool
    {
        return in_array($this->eventType, ['job', 'queued_job']);
    }

    public function isError(): bool
    {
        return $this->isRequest() && $this->statusCode && $this->statusCode >= 400;
    }

    public function isSlow(float $threshold = 1000.0): bool
    {
        return $this->duration && $this->duration > $threshold;
    }
}
