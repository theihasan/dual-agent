# Dual Agent Documentation

## Introduction

Dual Agent is a Laravel package that extends Laravel Nightwatch by automatically storing all monitoring data in your application's database. This enables local analytics, querying, and reporting while maintaining full compatibility with the original Nightwatch service.

## Installation

### Requirements

- PHP 8.2+
- Laravel 10.0+
- Laravel Nightwatch installed and configured

### Steps

1. **Install the package:**
   ```bash
   composer require theihasan/dual-agent
   ```

2. **Add service providers:**

   **For Laravel 10:**
   Add to `config/app.php`:
   ```php
   'providers' => [
       // ...
       \Laravel\Nightwatch\NightwatchServiceProvider::class,
       \Ihasan\DualAgent\DualAgentServiceProvider::class,
       // ...
   ],
   ```

   **For Laravel 11:**
   Add to `bootstrap/app.php`:
   ```php
   ->withProviders([
       \Laravel\Nightwatch\NightwatchServiceProvider::class,
       \Ihasan\DualAgent\DualAgentServiceProvider::class,
   ])
   ```

3. **Configure environment variables:**
   ```env
   # Nightwatch Configuration
   NIGHTWATCH_ENABLED=true
   NIGHTWATCH_TOKEN=your-nightwatch-token
   NIGHTWATCH_REQUEST_SAMPLE_RATE=1.0
   NIGHTWATCH_COMMAND_SAMPLE_RATE=1.0
   NIGHTWATCH_EXCEPTION_SAMPLE_RATE=1.0

   # Dual Agent Configuration
   DUAL_AGENT_ENABLED=true
   DUAL_AGENT_AUTO_CONFIGURE=true
   DUAL_AGENT_BUFFER_SIZE=100
   DUAL_AGENT_REQUEST_SAMPLE_RATE=1.0
   DUAL_AGENT_QUERY_SAMPLE_RATE=0.1
   DUAL_AGENT_EXCEPTION_SAMPLE_RATE=1.0
   DUAL_AGENT_JOB_SAMPLE_RATE=0.5
   DUAL_AGENT_LOG_SAMPLE_RATE=0.01
   DUAL_AGENT_CLEANUP_ENABLED=true
   DUAL_AGENT_RETENTION_DAYS=30
   DUAL_AGENT_AGGREGATION_ENABLED=true
   ```

4. **Run the installer:**
   ```bash
   php artisan dual-agent:install
   ```

5. **Verify installation:**
   ```bash
   php artisan dual-agent:status
   ```

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DUAL_AGENT_ENABLED` | `true` | Enable/disable database storage |
| `DUAL_AGENT_AUTO_CONFIGURE` | `true` | Auto-configure when Nightwatch detected |
| `DUAL_AGENT_BUFFER_SIZE` | `100` | Records to buffer before database insert |
| `DUAL_AGENT_REQUEST_SAMPLE_RATE` | `1.0` | Sampling rate for requests (0.0-1.0) |
| `DUAL_AGENT_QUERY_SAMPLE_RATE` | `0.1` | Sampling rate for database queries |
| `DUAL_AGENT_EXCEPTION_SAMPLE_RATE` | `1.0` | Sampling rate for exceptions |
| `DUAL_AGENT_JOB_SAMPLE_RATE` | `0.5` | Sampling rate for queue jobs |
| `DUAL_AGENT_LOG_SAMPLE_RATE` | `0.01` | Sampling rate for log entries |
| `DUAL_AGENT_CLEANUP_ENABLED` | `true` | Enable automatic data cleanup |
| `DUAL_AGENT_RETENTION_DAYS` | `30` | Days to retain metrics data |
| `DUAL_AGENT_AGGREGATION_ENABLED` | `true` | Enable metric aggregation |

### Config File

After installation, you can modify `config/dual-agent.php` for advanced configuration:

```php
return [
    'enabled' => env('DUAL_AGENT_ENABLED', true),
    'buffer_size' => env('DUAL_AGENT_BUFFER_SIZE', 100),
    'auto_configure' => env('DUAL_AGENT_AUTO_CONFIGURE', true),
    'filters' => [
        'event_types' => ['request', 'query', 'exception', 'job', 'log', 'cache', 'mail', 'notification', 'scheduled_task'],
        'sampling_rates' => [
            'request' => env('DUAL_AGENT_REQUEST_SAMPLE_RATE', 1.0),
            'query' => env('DUAL_AGENT_QUERY_SAMPLE_RATE', 0.1),
            'exception' => env('DUAL_AGENT_EXCEPTION_SAMPLE_RATE', 1.0),
            'job' => env('DUAL_AGENT_JOB_SAMPLE_RATE', 0.5),
            'log' => env('DUAL_AGENT_LOG_SAMPLE_RATE', 0.01),
        ],
    ],
    'database' => [
        'connection' => env('DUAL_AGENT_DB_CONNECTION', null),
        'cleanup' => [
            'enabled' => env('DUAL_AGENT_CLEANUP_ENABLED', true),
            'retention_days' => env('DUAL_AGENT_RETENTION_DAYS', 30),
            'batch_size' => env('DUAL_AGENT_CLEANUP_BATCH_SIZE', 1000),
        ],
    ],
];
```

## Usage

### Basic Querying

Use the Eloquent models to query your monitoring data:

```php
use Ihasan\DualAgent\Models\DualAgentMetric;
use Ihasan\DualAgent\Models\DualAgentAggregatedMetric;

// Get all requests from today
$requests = DualAgentMetric::requests()->today()->get();

// Find slow requests (> 1 second)
$slowRequests = DualAgentMetric::slowRequests(1000)->get();

// Get error rate for today
$errorCount = DualAgentMetric::requests()->errors()->today()->count();
$totalRequests = DualAgentMetric::requests()->today()->count();
$errorRate = $errorCount / $totalRequests * 100;

// Get recent exceptions
$exceptions = DualAgentMetric::exceptions()
    ->latest('event_timestamp')
    ->limit(10)
    ->get();

// Query aggregated metrics
$dailyStats = DualAgentAggregatedMetric::daily()
    ->requests()
    ->whereDate('metric_date', today())
    ->first();

if ($dailyStats) {
    echo "Today's average response time: {$dailyStats->avg_duration}ms";
    echo "Error rate: {$dailyStats->getErrorRate()}%";
}
```

### Available Scopes

The `DualAgentMetric` model includes helpful scopes:

#### Event Type Scopes
- `DualAgentMetric::requests()` - HTTP requests
- `DualAgentMetric::queries()` - Database queries
- `DualAgentMetric::exceptions()` - Exceptions
- `DualAgentMetric::jobs()` - Queue jobs
- `DualAgentMetric::cache()` - Cache operations
- `DualAgentMetric::mail()` - Email events
- `DualAgentMetric::logs()` - Log entries

#### Time-based Scopes
- `DualAgentMetric::today()`
- `DualAgentMetric::yesterday()`
- `DualAgentMetric::thisWeek()`
- `DualAgentMetric::thisMonth()`
- `DualAgentMetric::betweenDates($start, $end)`

#### Performance Scopes
- `DualAgentMetric::slowRequests($threshold)` - Default 1000ms
- `DualAgentMetric::slowQueries($threshold)` - Default 100ms
- `DualAgentMetric::errors()` - 4xx and 5xx responses
- `DualAgentMetric::failedJobs()` - Failed queue jobs

### Manual Data Storage

While automatic, you can manually store data:

```php
use Ihasan\DualAgent\Facades\DualAgent;

// Store custom metric data
DualAgent::writeNow([
    't' => 'custom_event',
    'message' => 'Custom monitoring data',
    'timestamp' => microtime(true),
    'custom_metadata' => [
        'user_id' => auth()->id(),
        'action' => 'important_action',
    ],
]);

// Check buffer status
$bufferSize = DualAgent::getBufferSize();
$bufferCount = DualAgent::getBufferCount();

// Force flush buffered data to database
DualAgent::digest();
```

## Database Schema

### dual_agent_metrics Table

Stores individual events with fields for:
- Request metrics: method, URL, status, duration, memory
- Query metrics: SQL, connection, duration
- Exception metrics: class, message, file, line, stack trace
- Job metrics: class, queue, status, attempts, duration
- Cache metrics: key, operation, store
- Mail metrics: class, recipients, subject
- Log metrics: level, message, context
- Performance stages: bootstrap, middleware, action, render timings

### dual_agent_aggregated_metrics Table

Stores computed statistics:
- Hourly, daily, weekly aggregations
- Performance percentiles (P95, P99)
- Error rates and status distributions
- Job success rates
- Top exceptions and trends

## Management Commands

### Installation & Setup
```bash
php artisan dual-agent:install          # Install and configure
php artisan dual-agent:install --force  # Force reinstall
```

### Status & Monitoring
```bash
php artisan dual-agent:status                    # Current status
php artisan dual-agent:status --detailed        # Detailed status
```

### Data Cleanup
```bash
php artisan dual-agent:cleanup  # Manual cleanup
```

Add to scheduler for automatic cleanup:
```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('dual-agent:cleanup')->daily();
}
```

## Performance Considerations

### Optimization Tips
- **Indexes**: All columns are indexed for performance
- **Batch Inserts**: Data inserted in batches
- **Buffer Management**: Prevents memory issues
- **Sampling**: Reduce rates for high-volume events
- **Cleanup**: Automatic old data removal

### Recommended Settings for High Traffic
```env
DUAL_AGENT_QUERY_SAMPLE_RATE=0.01
DUAL_AGENT_LOG_SAMPLE_RATE=0.001
DUAL_AGENT_BUFFER_SIZE=500
DUAL_AGENT_RETENTION_DAYS=7
```

## Troubleshooting

### Common Issues

#### Installation Fails
```bash
# Ensure Nightwatch is installed first
composer require laravel/nightwatch
php artisan migrate
```

#### Data Not Appearing
- Verify Nightwatch sampling rates are > 0
- Check `php artisan dual-agent:status --detailed`
- Ensure database connection works
- Clear cache: `php artisan cache:clear`

#### Infinite Logging Loop
- Handled automatically by filtering DualAgent logs
- If issues persist, reduce `DUAL_AGENT_LOG_SAMPLE_RATE`

#### Performance Issues
- Reduce sampling rates for verbose events
- Increase buffer size
- Enable cleanup

### Debug Logging
Enable debug logging:
```env
LOG_LEVEL=debug
```

Monitor logs:
```bash
tail -f storage/logs/laravel.log | grep -i "dual.*agent"
```

## API Reference

### Facades

#### DualAgent Facade
- `DualAgent::writeNow(array $data)` - Store data immediately
- `DualAgent::getBufferSize()` - Get buffer size
- `DualAgent::getBufferCount()` - Get buffered records count
- `DualAgent::digest()` - Flush buffer to database

### Models

#### DualAgentMetric
Extends Eloquent Model with custom scopes and methods.

#### DualAgentAggregatedMetric
Model for aggregated statistics with helper methods like `getErrorRate()`.

### Contracts
- `CustomIngestContract` - For custom ingest implementations
- `DataTransformerContract` - For data transformation

## Use Cases

- Performance monitoring and bottleneck identification
- Error tracking and pattern analysis
- User analytics and session data
- Database query optimization
- Queue job monitoring
- Custom dashboard building
- Historical trend analysis
- Alert setup based on metrics

## Security

- No sensitive data is stored without configuration
- Follow Laravel security best practices
- Data cleanup prevents indefinite storage

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

MIT License. See [LICENSE.md](LICENSE.md).