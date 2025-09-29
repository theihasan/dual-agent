<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Commands;

use Ihasan\DualAgent\Contracts\CustomIngestContract;
use Ihasan\DualAgent\Models\DualAgentMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Contracts\Ingest as NightwatchIngestContract;

class TestDualAgentCommand extends Command
{
    protected $signature = 'dual-agent:test-package
                           {--direct : Test database ingest directly without Nightwatch}
                           {--cleanup : Clean up test records after testing}
                           {--no-filters : Disable event filtering for testing}';

    protected $description = 'Test Dual Agent package functionality';

    public function handle(): int
    {
        $this->info('🧪 Testing Dual Agent Package...');
        $this->newLine();

        try {
            // Temporarily disable filtering if requested
            if ($this->option('no-filters')) {
                $this->temporarilyDisableFilters();
            }
            
            // Test 1: Check bindings and configuration
            $this->testBindingsAndConfiguration();
            
            // Test 2: Test database connection
            $this->testDatabaseConnection();
            
            // Test 3: Test direct database ingest
            $this->testDatabaseIngest();
            
            if (!$this->option('direct')) {
                // Test 4: Test Nightwatch integration
                $this->testNightwatchIntegration();
                
                // Test 5: Test composite ingest
                $this->testCompositeIngest();
            }
            
            // Test 6: Generate test metrics
            $this->generateTestMetrics();
            
            // Test 7: Verify data storage
            $this->verifyDataStorage();
            
            if ($this->option('cleanup')) {
                $this->cleanupTestData();
            }
            
            $this->displaySummary();
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->error("📍 File: {$e->getFile()}:{$e->getLine()}");
            
            if ($this->getOutput()->isVerbose()) {
                $this->error("Stack trace:");
                $this->error($e->getTraceAsString());
            }
            
            return Command::FAILURE;
        }
    }

    protected function temporarilyDisableFilters(): void
    {
        $this->info('🔧 Temporarily disabling event filters for testing...');
        
        // Set high sampling rates for testing
        config([
            'dual-agent.filters.sampling_rates.request' => 1.0,
            'dual-agent.filters.sampling_rates.query' => 1.0,
            'dual-agent.filters.sampling_rates.exception' => 1.0,
            'dual-agent.filters.sampling_rates.log' => 1.0,
            'dual-agent.filters.sampling_rates.test' => 1.0,
        ]);
        
        $this->line('✅ All event types set to 100% sampling rate');
    }

    protected function testBindingsAndConfiguration(): void
    {
        $this->section('📋 Testing Bindings and Configuration');

        // Test container bindings
        $customIngestBound = app()->bound(CustomIngestContract::class);
        $nightwatchIngestBound = app()->bound(NightwatchIngestContract::class);
        
        $this->line('Container Bindings:');
        $this->line("  • Custom Ingest Contract: " . ($customIngestBound ? '✅' : '❌'));
        $this->line("  • Nightwatch Ingest Contract: " . ($nightwatchIngestBound ? '✅' : '❌'));
        
        // Test configuration
        $config = config('dual-agent');
        $nightwatchConfig = config('nightwatch');
        
        $this->line('Configuration:');
        $this->line("  • Dual Agent Enabled: " . ($config['enabled'] ? '✅' : '❌'));
        $this->line("  • Buffer Size: {$config['buffer_size']}");
        $this->line("  • Nightwatch Enabled: " . ($nightwatchConfig['enabled'] ?? false ? '✅' : '❌'));
        $this->line("  • Nightwatch Token: " . (!empty($nightwatchConfig['token']) ? '✅ Present' : '❌ Missing'));
        
        // Show current sampling rates
        $sampling = $config['filters']['sampling_rates'] ?? [];
        $this->line('Sampling Rates:');
        foreach (['request', 'query', 'exception', 'log', 'test'] as $type) {
            $rate = ($sampling[$type] ?? 0) * 100;
            $this->line("  • {$type}: {$rate}%");
        }
        
        // Test service provider loading
        $providers = collect(app()->getLoadedProviders())->keys()
            ->filter(fn($provider) => str_contains($provider, 'Nightwatch') || str_contains($provider, 'DualAgent'))
            ->values()->all();
            
        $this->line('Loaded Providers:');
        foreach ($providers as $provider) {
            $this->line("  • {$provider}");
        }
    }

    protected function testDatabaseConnection(): void
    {
        $this->section('🗄️  Testing Database Connection');

        try {
            DB::connection()->getPdo();
            $this->info('✅ Database connection successful');
            
            // Test table existence
            $metricsExists = DB::getSchemaBuilder()->hasTable('dual_agent_metrics');
            $aggregatedExists = DB::getSchemaBuilder()->hasTable('dual_agent_aggregated_metrics');
            
            $this->line("Tables:");
            $this->line("  • dual_agent_metrics: " . ($metricsExists ? '✅' : '❌'));
            $this->line("  • dual_agent_aggregated_metrics: " . ($aggregatedExists ? '✅' : '❌'));
            
            if (!$metricsExists || !$aggregatedExists) {
                $this->warn('⚠️  Run migrations: php artisan migrate');
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Database connection failed: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function testDatabaseIngest(): void
    {
        $this->section('💾 Testing Database Ingest');

        try {
            $databaseIngest = app(CustomIngestContract::class);
            $this->info('✅ Database Ingest Class: ' . get_class($databaseIngest));
            $this->line('  • Enabled: ' . ($databaseIngest->isEnabled() ? '✅' : '❌'));
            $this->line('  • Buffer Size: ' . $databaseIngest->getBufferSize());
            $this->line('  • Buffer Count: ' . $databaseIngest->getBufferCount());
            
            // Test ping
            $databaseIngest->ping();
            $this->info('✅ Database ingest ping successful');
            
        } catch (\Exception $e) {
            $this->error("❌ Database ingest test failed: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function testNightwatchIntegration(): void
    {
        $this->section('🌙 Testing Nightwatch Integration');

        try {
            if (app()->bound(NightwatchIngestContract::class)) {
                $nightwatchIngest = app(NightwatchIngestContract::class);
                $this->info('✅ Nightwatch Ingest Class: ' . get_class($nightwatchIngest));
                
                // Check if it's our composite ingest
                if ($nightwatchIngest instanceof \Ihasan\DualAgent\Ingest\CompositeIngest) {
                    $this->info('✅ Using CompositeIngest (Dual monitoring active)');
                } else {
                    $this->warn('⚠️  Using original Nightwatch ingest (Dual Agent not integrated)');
                }
                
            } else {
                $this->warn('⚠️  Nightwatch Ingest contract not bound');
                $this->line('   This means Nightwatch is not properly configured or enabled');
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Nightwatch integration test failed: {$e->getMessage()}");
            // Don't throw here, as this is expected in database-only mode
        }
    }

    protected function testCompositeIngest(): void
    {
        $this->section('🔄 Testing Composite Ingest');

        try {
            if (app()->bound(NightwatchIngestContract::class)) {
                $ingest = app(NightwatchIngestContract::class);
                
                if ($ingest instanceof \Ihasan\DualAgent\Ingest\CompositeIngest) {
                    // Test composite functionality
                    $testRecord = [
                        't' => 'test',
                        'timestamp' => microtime(true),
                        'message' => 'Composite ingest test',
                        'trace' => 'composite-test-' . uniqid(),
                    ];
                    
                    $this->info('📝 Testing composite write...');
                    $ingest->write($testRecord);
                    $this->info('✅ Composite write completed');
                    
                } else {
                    $this->warn('⚠️  Composite ingest not active');
                }
            } else {
                $this->warn('⚠️  Skipping composite test (Nightwatch not bound)');
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Composite ingest test failed: {$e->getMessage()}");
            // Don't throw, continue with other tests
        }
    }

    protected function generateTestMetrics(): void
    {
        $this->section('📊 Generating Test Metrics');

        try {
            $databaseIngest = app(CustomIngestContract::class);
            
            // Generate different types of test records
            $testRecords = [
                // Request record
                [
                    't' => 'request',
                    'timestamp' => microtime(true),
                    'method' => 'GET',
                    'url' => '/test-dual-agent-request',
                    'status_code' => 200,
                    'duration' => 125.5,
                    'trace' => 'test-request-' . uniqid(),
                ],
                // Query record
                [
                    't' => 'query',
                    'timestamp' => microtime(true),
                    'sql' => 'SELECT * FROM users LIMIT 10',
                    'connection' => 'mysql',
                    'duration' => 15.2,
                    'trace' => 'test-query-' . uniqid(),
                ],
                // Exception record
                [
                    't' => 'exception',
                    'timestamp' => microtime(true),
                    'class' => 'TestException',
                    'message' => 'This is a test exception',
                    'file' => '/test/file.php',
                    'line' => 123,
                    'trace' => 'test-exception-' . uniqid(),
                ],
                // Log record
                [
                    't' => 'log',
                    'timestamp' => microtime(true),
                    'level' => 'info',
                    'message' => 'Test log message',
                    'context' => ['test' => true],
                    'trace' => 'test-log-' . uniqid(),
                ],
                // Custom test record
                [
                    't' => 'test',
                    'timestamp' => microtime(true),
                    'message' => 'Direct test from dual-agent:test-package command',
                    'test_data' => ['batch' => 'test-metrics', 'version' => '1.0.0'],
                    'trace' => 'test-custom-' . uniqid(),
                ],
            ];

            foreach ($testRecords as $i => $record) {
                $this->line("📝 Writing {$record['t']} record...");
                $databaseIngest->writeNow($record); // Use writeNow to bypass buffering for testing
            }
            
            $this->info('✅ Test metrics generated successfully');
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to generate test metrics: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function verifyDataStorage(): void
    {
        $this->section('✅ Verifying Data Storage');

        try {
            $totalCount = DualAgentMetric::count();
            $this->info("📊 Total metrics in database: {$totalCount}");
            
            if ($totalCount === 0) {
                $this->warn('⚠️  No metrics found in database. Data may not be saving.');
                return;
            }
            
            // Count by event type
            $eventCounts = DualAgentMetric::selectRaw('event_type, COUNT(*) as count')
                ->groupBy('event_type')
                ->orderBy('count', 'desc')
                ->get();
                
            $this->line('Event Type Counts:');
            foreach ($eventCounts as $event) {
                $this->line("  • {$event->event_type}: {$event->count}");
            }
            
            // Show recent records
            $recent = DualAgentMetric::latest('event_timestamp')
                ->limit(5)
                ->get(['event_type', 'event_timestamp', 'trace_id', 'duration']);
                
            if ($recent->isNotEmpty()) {
                $this->line('Recent Records:');
                foreach ($recent as $record) {
                    // Fix: Safely handle duration formatting
                    $duration = 'N/A';
                    if ($record->duration !== null) {
                        $durationValue = is_numeric($record->duration) ? (float) $record->duration : null;
                        if ($durationValue !== null) {
                            $duration = number_format($durationValue, 2) . 'ms';
                        }
                    }
                    $this->line("  • {$record->event_type} | {$record->event_timestamp} | {$duration}");
                }
            }
            
            // Test specific functionality
            $testRecords = DualAgentMetric::where('event_type', 'test')->count();
            $requestRecords = DualAgentMetric::requests()->count();
            $queryRecords = DualAgentMetric::queries()->count();
            
            $this->line('Model Scopes Test:');
            $this->line("  • Test records: {$testRecords}");
            $this->line("  • Request records: {$requestRecords}");
            $this->line("  • Query records: {$queryRecords}");
            
        } catch (\Exception $e) {
            $this->error("❌ Data verification failed: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function cleanupTestData(): void
    {
        $this->section('🧹 Cleaning Up Test Data');

        try {
            $testRecords = DualAgentMetric::where('event_type', 'test')
                ->orWhere('trace_id', 'like', 'test-%')
                ->orWhere('trace_id', 'like', 'composite-test-%');
                
            $count = $testRecords->count();
            $testRecords->delete();
            
            $this->info("🗑️  Deleted {$count} test records");
            
        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");
        }
    }

    protected function displaySummary(): void
    {
        $this->section('📋 Test Summary');

        $totalMetrics = DualAgentMetric::count();
        $isNightwatchBound = app()->bound(NightwatchIngestContract::class);
        $isDualMonitoring = false;
        
        if ($isNightwatchBound) {
            $ingest = app(NightwatchIngestContract::class);
            $isDualMonitoring = $ingest instanceof \Ihasan\DualAgent\Ingest\CompositeIngest;
        }

        $status = [
            ['Component', 'Status'],
            ['Database Storage', $totalMetrics > 0 ? '✅ Working' : '❌ No Data'],
            ['Nightwatch Integration', $isNightwatchBound ? '✅ Available' : '⚠️  Not Available'],
            ['Dual Monitoring', $isDualMonitoring ? '✅ Active' : '⚠️  Database Only'],
        ];

        $this->table(['Component', 'Status'], array_slice($status, 1));

        if ($totalMetrics > 0) {
            $this->info('🎉 Dual Agent is working! Data is being stored in your database.');
        } else {
            $this->warn('⚠️  No data found. Check your configuration and try making some requests.');
        }

        if (!$isNightwatchBound) {
            $this->warn('💡 Nightwatch integration not available. You\'re in database-only mode.');
            $this->line('   This is fine for local storage, but you won\'t get Nightwatch platform data.');
        }

        $this->newLine();
        $this->info('📚 Next steps:');
        $this->line('• Check status: php artisan dual-agent:status --detailed');
        $this->line('• Query data: php artisan tinker → DualAgentMetric::count()');
        $this->line('• Make requests to generate more data');
    }

    protected function section(string $title): void
    {
        $this->newLine();
        $this->line("<fg=blue;options=bold>{$title}</>");
        $this->line(str_repeat('─', strlen(strip_tags($title))));
        $this->newLine();
    }
}