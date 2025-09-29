<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'dual-agent:install 
                           {--force : Force installation even if already installed}
                           {--without-optimization : Skip optimization steps}';

    protected $description = 'Install and configure the Dual Agent package';

    public function handle(): int
    {
        $this->info('🚀 Installing Dual Agent...');
        $this->newLine();

        try {
            if (!$this->checkNightwatchInstallation()) {
                $this->error('❌ Laravel Nightwatch is not installed or configured.');
                $this->info('📝 Please install Laravel Nightwatch first: composer require laravel/nightwatch');
                return Command::FAILURE;
            }

            $this->publishConfiguration();
            $this->updateEnvironmentFile();
            $this->runMigrations();

            if (!$this->option('without-optimization')) {
                $this->optimizeApplication();
            }

            $this->displaySuccessMessage();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Installation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function checkNightwatchInstallation(): bool
    {
        if (!class_exists(\Laravel\Nightwatch\NightwatchServiceProvider::class)) {
            return false;
        }

        if (!Config::has('nightwatch')) {
            return false;
        }

        if (!Config::get('nightwatch.enabled', false)) {
            return false;
        }

        return !empty(Config::get('nightwatch.token'));
    }

    protected function publishConfiguration(): void
    {
        $this->info('📋 Publishing configuration...');

        $this->call('vendor:publish', [
            '--provider' => 'Ihasan\DualAgent\DualAgentServiceProvider',
            '--tag' => 'dual-agent-config',
            '--force' => $this->option('force'),
        ]);

        $this->info('✅ Configuration published successfully.');
    }

    protected function updateEnvironmentFile(): void
    {
        $this->info('🔧 Updating environment variables...');

        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->warn('⚠️  .env file not found. Skipping environment update.');
            return;
        }

        $envContent = File::get($envPath);
        
        $variablesToAdd = [
            'DUAL_AGENT_ENABLED' => 'true',
            'DUAL_AGENT_AUTO_CONFIGURE' => 'true',
            'DUAL_AGENT_BUFFER_SIZE' => '100',
            'DUAL_AGENT_CLEANUP_ENABLED' => 'true',
            'DUAL_AGENT_RETENTION_DAYS' => '30',
            'DUAL_AGENT_AGGREGATION_ENABLED' => 'true',
        ];

        $modified = false;
        foreach ($variablesToAdd as $key => $value) {
            if (!str_contains($envContent, $key . '=')) {
                $envContent .= "{$key}={$value}\n";
                $modified = true;
            }
        }

        if ($modified) {
            File::put($envPath, $envContent);
            $this->info('✅ Environment variables updated.');
        } else {
            $this->info('ℹ️  Environment variables already present.');
        }
    }

    protected function runMigrations(): void
    {
        $this->info('🗄️  Running migrations...');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->info('✅ Migrations completed successfully.');
        } catch (\Exception $e) {
            $this->warn("⚠️  Migrations failed: {$e->getMessage()}");
        }
    }

    protected function optimizeApplication(): void
    {
        $this->info('⚡ Optimizing application...');

        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            $this->info('✅ Application optimized.');
        } catch (\Exception $e) {
            $this->warn("⚠️  Optimization failed: {$e->getMessage()}");
        }
    }

    protected function displaySuccessMessage(): void
    {
        $this->newLine();
        $this->info('🎉 Dual Agent installed successfully!');
        $this->newLine();
        
        $this->table(['Component', 'Status'], [
            ['Configuration', '✅ Published to config/dual-agent.php'],
            ['Environment', '✅ Variables added to .env file'],
            ['Migrations', '✅ Database tables created'],
            ['Integration', '✅ Nightwatch dual monitoring active'],
        ]);

        $this->newLine();
        $this->info('🚀 Your application now stores Nightwatch metrics in your database!');
        $this->newLine();

        $this->warn('💡 Next Steps:');
        $this->line('1. Check status: php artisan dual-agent:status');
        $this->line('2. Review configuration: config/dual-agent.php');
        $this->line('3. Query metrics: Ihasan\DualAgent\Models\DualAgentMetric');
        $this->line('4. View aggregated data: Ihasan\DualAgent\Models\DualAgentAggregatedMetric');
    }
}