<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Support;

use Ihasan\DualAgent\Contracts\DataTransformerContract;
use Ihasan\DualAgent\DTOs\MetricDto;

class DataTransformer implements DataTransformerContract
{
    public function transform(array $record): MetricDto
    {
        return MetricDto::fromNightwatchRecord($record);
    }

    public function supports(array $record): bool
    {
        return isset($record['t']) || isset($record['event_type']);
    }

    public function shouldSendRecord(array $record): bool
    {
        $eventType = $record['t'] ?? $record['event_type'] ?? 'unknown';
        
        if ($eventType === 'log') {
            $message = $record['message'] ?? '';
            $context = $record['context'] ?? [];
            if (strpos($message, '[DualAgent]') !== false || 
                (is_array($context) && isset($context[0]) && strpos($context[0], '[DualAgent]') !== false)) {
                return false;
            }
        }
        
        $filters = config('dual-agent.filters', []);

        // Check if event type is allowed
        if (isset($filters['event_types']) && !empty($filters['event_types'])) {
            if (!in_array($eventType, $filters['event_types'])) {
                return false;
            }
        }

        // Apply sampling rate
        $samplingRate = $filters['sampling_rates'][$eventType] ?? 1.0;
        if ($samplingRate < 1.0 && mt_rand() / mt_getrandmax() > $samplingRate) {
            return false;
        }

        // Skip if explicitly disabled for specific types
        $disabledTypes = $filters['disabled_types'] ?? [];
        if (in_array($eventType, $disabledTypes)) {
            return false;
        }

        return true;
    }
}