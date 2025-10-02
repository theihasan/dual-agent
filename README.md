# Dual Agent for Laravel Nightwatch

<div align="center">

[![Latest Version on Packagist](https://img.shields.io/packagist/v/theihasan/dual-agent.svg?style=flat-square)](https://packagist.org/packages/theihasan/dual-agent)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/theihasan/dual-agent/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/theihasan/dual-agent/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/theihasan/dual-agent.svg?style=flat-square)](https://packagist.org/packages/theihasan/dual-agent)
[![License](https://img.shields.io/packagist/l/theihasan/dual-agent.svg?style=flat-square)](https://packagist.org/packages/theihasan/dual-agent)

</div>

Dual Agent is a Laravel package that automatically saves all Nightwatch monitoring data to your application's database while maintaining full compatibility with the original Nightwatch service.

## Requirements

- PHP 8.2+
- Laravel 10.0+
- [Laravel Nightwatch](https://nightwatch.laravel.com) installed and configured with sampling rates set appropriately

## Installation

1. Install the package:
   ```bash
   composer require theihasan/dual-agent
   ```

2. Add service providers to `config/app.php` (after Nightwatch):
   ```php
   'providers' => [
       // ...
       \Laravel\Nightwatch\NightwatchServiceProvider::class,
       \Ihasan\DualAgent\DualAgentServiceProvider::class,
       // ...
   ],
   ```

3. Configure your `.env` file:
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
   ```

4. Run the installer:
   ```bash
   php artisan dual-agent:install
   ```

## Configuration

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

## Basic Usage

Query your monitoring data using the provided models:

```php
use Ihasan\DualAgent\Models\DualAgentMetric;

// Get today's requests
$requests = DualAgentMetric::requests()->today()->get();

// Find slow requests
$slowRequests = DualAgentMetric::slowRequests(1000)->get();

// Get recent exceptions
$exceptions = DualAgentMetric::exceptions()->latest()->limit(10)->get();
```

See full documentation for more query examples and available scopes.

## Common Issues

### Data Not Appearing
Ensure Nightwatch sampling rates are set appropriately in your `.env`:
```
NIGHTWATCH_REQUEST_SAMPLE_RATE=1.0
NIGHTWATCH_COMMAND_SAMPLE_RATE=1.0
NIGHTWATCH_EXCEPTION_SAMPLE_RATE=1.0
```

### Installation Fails
Make sure Laravel Nightwatch is installed first:
```bash
composer require laravel/nightwatch
php artisan migrate
```

## Links to Full Documentation

- [Complete Installation Guide](https://github.com/theihasan/dual-agent/blob/main/docs/installation.md)
- [Configuration Reference](https://github.com/theihasan/dual-agent/blob/main/docs/configuration.md)
- [Querying Data](https://github.com/theihasan/dual-agent/blob/main/docs/querying.md)
- [Troubleshooting](https://github.com/theihasan/dual-agent/blob/main/docs/troubleshooting.md)
- [API Reference](https://github.com/theihasan/dual-agent/blob/main/docs/api.md)

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

## Security

If you discover any security-related issues, please email theihasan@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Ihasan](https://github.com/theihasan)
- Built for [Laravel Nightwatch](https://nightwatch.laravel.com)
- Inspired by the Laravel community