<?php

declare(strict_types=1);

namespace Ihasan\DualAgent;

use Ihasan\DualAgent\Commands\InstallCommand;
use Ihasan\DualAgent\Commands\StatusCommand;
use Ihasan\DualAgent\Commands\TestDualAgentCommand;
use Ihasan\DualAgent\Contracts\CustomIngestContract;
use Ihasan\DualAgent\Ingest\CompositeIngest;
use Ihasan\DualAgent\Ingest\DatabaseIngest;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Contracts\Ingest as NightwatchIngestContract;

class DualAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/dual-agent.php',
            'dual-agent'
        );

        $this->registerDatabaseIngest();
        $this->registerCompositeIngest();
        $this->registerFacades();
    }

    public function boot(): void
    {
        $this->publishConfiguration();
        $this->loadMigrations();
        $this->registerCommands();
        $this->autoConfigureIfNightwatchDetected();
    }

    protected function registerDatabaseIngest(): void
    {
        $this->app->singleton(CustomIngestContract::class, function ($app) {
            $config = $app['config']['dual-agent'];

            return new DatabaseIngest(
                enabled: (bool) ($config['enabled'] ?? true),
                bufferSize: (int) ($config['buffer_size'] ?? 100)
            );
        });

        $this->app->alias(CustomIngestContract::class, 'dual-agent.database-ingest');
    }

    protected function registerCompositeIngest(): void
    {        
        $this->app->booted(function ($app) {
            if ($app->bound('dual_agent_composite_registered')) {
                return;
            }
            
            if (!$app['config']['dual-agent.enabled'] ?? false) {
                return;
            }

            if (!$this->isNightwatchProperlyConfigured($app)) {
                return;
            }

            // Check if Nightwatch Core is bound
            if (!$app->bound(\Laravel\Nightwatch\Core::class)) {
                return;
            }

            try {
                // Get the nightwatch ingest from the Core
                $core = $app->make(\Laravel\Nightwatch\Core::class);
                $originalIngest = $core->ingest;
                $databaseIngest = $app->make(CustomIngestContract::class);
                
               
                $compositeIngest = new CompositeIngest($originalIngest, $databaseIngest);
                
                $core->ingest = $compositeIngest;
                
                $app->instance(NightwatchIngestContract::class, $compositeIngest);
                

                $app->instance('dual_agent_composite_registered', true);
                
                \Illuminate\Support\Facades\Log::info('[DualAgent] CompositeIngest successfully registered and Core ingest replaced', [
                    'original_ingest' => get_class($originalIngest),
                    'composite_ingest' => get_class($compositeIngest),
                ]);
                
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('[DualAgent] Failed to register CompositeIngest', [
                    'error' => $e->getMessage(),
                ]);
                // If something goes wrong, silently continue
                // This ensures the app doesn't break if Nightwatch isn't properly configured
            }
        });
    }

    protected function registerFacades(): void
    {
        $this->app->bind('dual-agent', function ($app) {
            return $app->make(CustomIngestContract::class);
        });
    }

    protected function publishConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/dual-agent.php' => config_path('dual-agent.php'),
            ], 'dual-agent-config');

            $this->publishes([
                __DIR__.'/../config/dual-agent.php' => config_path('dual-agent.php'),
            ], 'config');
        }
    }

    protected function loadMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                StatusCommand::class,
                TestDualAgentCommand::class,
            ]);
        }
    }

    protected function autoConfigureIfNightwatchDetected(): void
    {
        if (!$this->app['config']['dual-agent.auto_configure'] ?? true) {
            return;
        }

        if (!$this->isNightwatchProperlyConfigured($this->app)) {
            return;
        }

        $this->autoEnableIfNotExplicitlyDisabled();
        $this->setDefaultConfigurationValues();
    }

    protected function isNightwatchProperlyConfigured($app): bool
    {
        return class_exists(\Laravel\Nightwatch\NightwatchServiceProvider::class)
            && $app['config']['nightwatch.enabled'] ?? false
            && !empty($app['config']['nightwatch.token']);
    }

    protected function autoEnableIfNotExplicitlyDisabled(): void
    {
        if (!$this->app->config->has('dual-agent.enabled')) {
            $this->app['config']->set('dual-agent.enabled', true);
        }
    }

    protected function setDefaultConfigurationValues(): void
    {
        $config = $this->app['config'];

        if (!$config->has('dual-agent.buffer_size')) {
            $config->set('dual-agent.buffer_size', config('dual-agent.buffer_size', 100));
        }
    }

    public function provides(): array
    {
        return [
            CustomIngestContract::class,
            'dual-agent.database-ingest',
            'dual-agent',
        ];
    }
}