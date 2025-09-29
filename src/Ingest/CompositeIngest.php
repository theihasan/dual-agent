<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Ingest;

use Ihasan\DualAgent\Contracts\CustomIngestContract;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Contracts\Ingest as NightwatchIngestContract;

class CompositeIngest implements NightwatchIngestContract
{
    public function __construct(
        protected NightwatchIngestContract $nightwatchIngest,
        protected CustomIngestContract $databaseIngest
    ) {

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = $trace[1] ?? [];
        
        Log::info('[DualAgent] CompositeIngest created', [
            'nightwatch_class' => get_class($this->nightwatchIngest),
            'database_class' => get_class($this->databaseIngest),
            'database_enabled' => $this->databaseIngest->isEnabled(),
            'called_from' => ($caller['class'] ?? 'unknown') . '::' . ($caller['function'] ?? 'unknown'),
            'instance_id' => spl_object_hash($this),
        ]);
    }

    public function write(array $record): void
    {
        // Debug logging
        Log::info('[DualAgent] CompositeIngest::write called', [
            'record_type' => $record['t'] ?? 'unknown',
            'database_enabled' => $this->databaseIngest->isEnabled(),
        ]);

        // Always write to Nightwatch first (primary monitoring)
        try {
            $this->nightwatchIngest->write($record);
            Log::debug('[DualAgent] ✅ Nightwatch write successful');
        } catch (\Exception $e) {
            Log::error('[DualAgent] ❌ Nightwatch ingest write failed', [
                'error' => $e->getMessage(),
                'record_type' => $record['t'] ?? 'unknown',
            ]);
        }

        // Then write to our database storage (secondary/additional monitoring)
        if ($this->databaseIngest->isEnabled()) {
            try {
                $this->databaseIngest->write($record);
                Log::debug('[DualAgent] ✅ Database write successful');
            } catch (\Exception $e) {
                Log::warning('[DualAgent] ❌ Database ingest write failed', [
                    'error' => $e->getMessage(),
                    'record_type' => $record['t'] ?? 'unknown',
                ]);
            }
        } else {
            Log::debug('[DualAgent] Database ingest is disabled');
        }
    }

    public function writeNow(array $record): void
    {
        Log::info('[DualAgent] CompositeIngest::writeNow called', [
            'record_type' => $record['t'] ?? 'unknown',
        ]);

        try {
            $this->nightwatchIngest->writeNow($record);
        } catch (\Exception $e) {
            Log::error('[DualAgent] Nightwatch ingest writeNow failed', [
                'error' => $e->getMessage(),
                'record_type' => $record['t'] ?? 'unknown',
            ]);
        }

        if ($this->databaseIngest->isEnabled()) {
            try {
                $this->databaseIngest->writeNow($record);
            } catch (\Exception $e) {
                Log::warning('[DualAgent] Database ingest writeNow failed', [
                    'error' => $e->getMessage(),
                    'record_type' => $record['t'] ?? 'unknown',
                ]);
            }
        }
    }

    public function ping(): void
    {
        Log::info('[DualAgent] CompositeIngest::ping called');

        try {
            $this->nightwatchIngest->ping();
        } catch (\Exception $e) {
            Log::debug('[DualAgent] Nightwatch ping failed', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($this->databaseIngest->isEnabled()) {
            try {
                $this->databaseIngest->ping();
            } catch (\Exception $e) {
                Log::debug('[DualAgent] Database ping failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function shouldDigest(bool $bool = true): void
    {
        Log::info('[DualAgent] CompositeIngest::shouldDigest called', ['value' => $bool]);

        try {
            $this->nightwatchIngest->shouldDigest($bool);
        } catch (\Exception $e) {
            Log::error('[DualAgent] Nightwatch shouldDigest failed', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($this->databaseIngest->isEnabled()) {
            try {
                $this->databaseIngest->shouldDigest($bool);
            } catch (\Exception $e) {
                Log::warning('[DualAgent] Database shouldDigest failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function shouldDigestWhenBufferIsFull(bool $bool = true): void
    {
        Log::info('[DualAgent] CompositeIngest::shouldDigestWhenBufferIsFull called', ['value' => $bool]);

        try {
            $this->nightwatchIngest->shouldDigestWhenBufferIsFull($bool);
        } catch (\Exception $e) {
            Log::error('[DualAgent] Nightwatch shouldDigestWhenBufferIsFull failed', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($this->databaseIngest->isEnabled()) {
            try {
                $this->databaseIngest->shouldDigestWhenBufferIsFull($bool);
            } catch (\Exception $e) {
                Log::warning('[DualAgent] Database shouldDigestWhenBufferIsFull failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function digest(): void
    {
        Log::info('[DualAgent] CompositeIngest::digest called');

        try {
            $this->nightwatchIngest->digest();
        } catch (\Exception $e) {
            Log::error('[DualAgent] Nightwatch digest failed', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($this->databaseIngest->isEnabled()) {
            try {
                $this->databaseIngest->digest();
            } catch (\Exception $e) {
                Log::warning('[DualAgent] Database digest failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function flush(): void
    {
        Log::info('[DualAgent] CompositeIngest::flush called');

        try {
            $this->nightwatchIngest->flush();
        } catch (\Exception $e) {
            Log::error('[DualAgent] Nightwatch flush failed', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($this->databaseIngest->isEnabled()) {
            try {
                $this->databaseIngest->flush();
            } catch (\Exception $e) {
                Log::warning('[DualAgent] Database flush failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function getDatabaseIngest(): CustomIngestContract
    {
        return $this->databaseIngest;
    }

    public function getNightwatchIngest(): NightwatchIngestContract
    {
        return $this->nightwatchIngest;
    }
}