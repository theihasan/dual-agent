<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Commands;

use Ihasan\DualAgent\Contracts\CustomIngestContract;
use Ihasan\DualAgent\Models\DualAgentMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StatusCommand extends Command
{
    protected $signature = 'dual-agent:status {--detailed : Show detailed status information}';

    protected $description = 'Check the status of Dual Agent';

    public function handle(): int
    {
        $this->info('🔍 Dual Agent Status Check');
        $this->newLine();

        $this->checkPackageStatus();
        
        if ($this->option('detailed')) {
            $this->checkDetailedStatus();
        }
        
        $this->checkDatabaseStatus();
        $this->checkMetricsStatus();
        $this->newLine();
        
        $this->info('✅ Status check completed.');
        
        return Command::SUCCESS;
    }

    protected function checkPackageStatus(): void
    {
        $this->section('📦 Package Configuration');

        $enabled = Config::get('dual-agent.enabled', false);
        $nightwatchDetected = $this->isNightwatchInstalled();
        $nightwatchConfigured = $this->isNightwatchConfigured();
        $tablesExist = $this->checkTablesExist();

        $status = [
            ['Package Enabled', $enabled ? '✅ Yes' : '❌ No'],
            ['Nightwatch Detected', $nightwatchDetected ? '✅ Yes' : '❌ No'],
            ['Nightwatch Configured', $nightwatchConfigured ? '✅ Yes' : '❌ No'],
            ['Database Tables', $tablesExist ? '✅ Created' : '❌ Missing'],
            ['Auto-Configuration', Config::get('dual-agent.auto_configure', false) ? '✅ Yes' : '❌ No'],
        ];

        $this->table(['Setting', 'Status'], $status);

        if ($enabled && $nightwatchConfigured && $tablesExist) {
            $this->info('✅ Package is properly configured and ready to use');
        } else {
            $this->warn('⚠️  Package needs configuration');
            if (!$nightwatchDetected) {
                $this->line('   Install Nightwatch: composer require laravel/nightwatch');
            }
            if (!$nightwatchConfigured) {
                $this->line('   Configure Nightwatch in your .env file');
            }
            if (!$tablesExist) {
                $this->line('   Run migrations: php artisan migrate');
            }
        }
    }

    protected function checkDetailedStatus(): void
    {
        $this->section('🔧 Detailed Configuration');

        $config = Config::get('dual-agent');

        $details = [
            ['Buffer Size', (string) ($config['buffer_size'] ?? 100)],
            ['Auto Configure', ($config['auto_configure'] ?? true) ? 'Enabled' : 'Disabled'],
            ['Cleanup Enabled', ($config['database']['cleanup']['enabled'] ?? false) ? 'Enabled' : 'Disabled'],
            ['Retention Days', (string) ($config['database']['cleanup']['retention_days'] ?? 30)],
            ['Aggregation Enabled', ($config['aggregation']['enabled'] ?? false) ? 'Enabled' : 'Disabled'],
            ['Package Version', $config['version'] ?? '1.0.0'],
        ];

        $this->table(['Setting', 'Value'], $details);

        $this->section('🎯 Event Filters');

        $filters = $config['filters'] ?? [];
        $eventTypes = $filters['event_types'] ?? [];
        $samplingRates = $filters['sampling_rates'] ?? [];

        $this->line('Monitored Event Types: ' . (empty($eventTypes) ? 'All' : implode(', ', $eventTypes)));

        if (!empty($samplingRates)) {
            $this->newLine();
            $this->line('Sampling Rates:');
            foreach ($samplingRates as $event => $rate) {
                $percentage = ($rate * 100);
                $this->line("  • {$event}: {$percentage}%");
            }
        }
    }

    protected function checkDatabaseStatus(): void
    {
        $this->section('🗄️  Database Status');

        try {
            // Test database connection
            DB::connection()->getPdo();
            $this->info('✅ Database: Connected');
            
            // Check table existence
            $metricsTable = Schema::hasTable('dual_agent_metrics');
            $aggregatedTable = Schema::hasTable('dual_agent_aggregated_metrics');
            
            $this->info('✅ Metrics Table: ' . ($metricsTable ? 'Exists' : '❌ Missing'));
            $this->info('✅ Aggregated Table: ' . ($aggregatedTable ? 'Exists' : '❌ Missing'));
            
        } catch (\Exception $e) {
            $this->error("❌ Database: Connection failed - {$e->getMessage()}");
        }

        // Check Custom Ingest
        try {
            /** @var CustomIngestContract $databaseIngest */
            $databaseIngest = app(CustomIngestContract::class);
            
            if ($databaseIngest->isEnabled()) {
                $bufferStatus = "{$databaseIngest->getBufferCount()}/{$databaseIngest->getBufferSize()} records";
                $this->info("✅ Database Ingest: Active (Buffer: {$bufferStatus})");
            } else {
                $this->warn('⚠️  Database Ingest: Disabled');
            }
        } catch (\Exception $e) {
            $this->error("❌ Database Ingest: Error - {$e->getMessage()}");
        }
    }

    protected function checkMetricsStatus(): void
    {
        $this->section('📊 Metrics Status');

        try {
            if (!Schema::hasTable('dual_agent_metrics')) {
                $this->warn('⚠️  Metrics table not found. Run: php artisan migrate');
                return;
            }

            $totalMetrics = DualAgentMetric::count();
            $todayMetrics = DualAgentMetric::today()->count();
            
            $eventTypeCounts = DualAgentMetric::selectRaw('event_type, COUNT(*) as count')
                ->groupBy('event_type')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get();

            $this->table(['Metric', 'Value'], [
                ['Total Records', number_format($totalMetrics)],
                ['Today Records', number_format($todayMetrics)],
            ]);

            if ($eventTypeCounts->isNotEmpty()) {
                $this->newLine();
                $this->line('Top Event Types:');
                
                $eventData = [];
                foreach ($eventTypeCounts as $event) {
                    $eventData[] = [$event->event_type, number_format($event->count)];
                }
                
                $this->table(['Event Type', 'Count'], $eventData);
            }

            // Show recent activity
            $recentMetrics = DualAgentMetric::latest('event_timestamp')->limit(5)->get();
            
            if ($recentMetrics->isNotEmpty()) {
                $this->newLine();
                $this->line('Recent Activity:');
                
                $recentData = [];
                foreach ($recentMetrics as $metric) {
                    $recentData[] = [
                        $metric->event_type,
                        $metric->event_timestamp->format('Y-m-d H:i:s'),
                        $metric->duration ? number_format($metric->duration, 2) . 'ms' : 'N/A',
                    ];
                }
                
                $this->table(['Type', 'Time', 'Duration'], $recentData);
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to retrieve metrics: {$e->getMessage()}");
        }

        // Show integration status
        $this->newLine();
        if ($this->isNightwatchConfigured() && Config::get('dual-agent.enabled', false)) {
            $this->info('🎯 Integration Status: Dual monitoring active (Nightwatch + Database)');
        } elseif ($this->isNightwatchConfigured()) {
            $this->warn('⚠️  Integration Status: Nightwatch only (Dual Agent disabled)');
        } else {
            $this->error('❌ Integration Status: Not properly configured');
        }
    }

    protected function isNightwatchInstalled(): bool
    {
        return class_exists(\Laravel\Nightwatch\NightwatchServiceProvider::class);
    }

    protected function isNightwatchConfigured(): bool
    {
        return $this->isNightwatchInstalled() 
            && Config::has('nightwatch')
            && Config::get('nightwatch.enabled', false)
            && !empty(Config::get('nightwatch.token'));
    }

    protected function checkTablesExist(): bool
    {
        try {
            return Schema::hasTable('dual_agent_metrics') 
                && Schema::hasTable('dual_agent_aggregated_metrics');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function section(string $title): void
    {
        $this->newLine();
        $this->line("<fg=blue;options=bold>{$title}</>");
        $this->line(str_repeat('─', strlen(strip_tags($title))));
        $this->newLine();
    }
}