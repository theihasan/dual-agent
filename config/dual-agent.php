<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dual Agent Enable/Disable
    |--------------------------------------------------------------------------
    |
    | Enable or disable the dual agent monitoring. When disabled, only
    | the original Nightwatch agent will be used.
    |
    */
    'enabled' => (bool) env('DUAL_AGENT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-Configuration
    |--------------------------------------------------------------------------
    |
    | Automatically configure the package when Nightwatch is detected.
    |
    */
    'auto_configure' => (bool) env('DUAL_AGENT_AUTO_CONFIGURE', true),

    /*
    |--------------------------------------------------------------------------
    | Buffer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the buffer behavior for database storage.
    |
    */
    'buffer_size' => (int) env('DUAL_AGENT_BUFFER_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Data Filtering
    |--------------------------------------------------------------------------
    |
    | Configure which events to store in the database and sampling rates.
    |
    */
    'filters' => [
        'event_types' => [
            'request', 'query', 'exception', 'job', 'log', 'cache',
            'mail', 'notification', 'scheduled_task', 'test'
        ],
        'sampling_rates' => [
            'request' => (float) env('DUAL_AGENT_REQUEST_SAMPLE_RATE', 1.0),
            'query' => (float) env('DUAL_AGENT_QUERY_SAMPLE_RATE', 0.1),
            'exception' => (float) env('DUAL_AGENT_EXCEPTION_SAMPLE_RATE', 1.0),
            'job' => (float) env('DUAL_AGENT_JOB_SAMPLE_RATE', 0.5),
            'log' => (float) env('DUAL_AGENT_LOG_SAMPLE_RATE', 0.01),
            'cache' => (float) env('DUAL_AGENT_CACHE_SAMPLE_RATE', 0.05),
            'mail' => (float) env('DUAL_AGENT_MAIL_SAMPLE_RATE', 0.1),
            'notification' => (float) env('DUAL_AGENT_NOTIFICATION_SAMPLE_RATE', 0.1),
            'scheduled_task' => (float) env('DUAL_AGENT_SCHEDULED_TASK_SAMPLE_RATE', 0.2),
            'test' => (float) env('DUAL_AGENT_TEST_SAMPLE_RATE', 1.0),
        ],
        'disabled_types' => env('DUAL_AGENT_DISABLED_TYPES', []),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database storage and cleanup settings.
    |
    */
    'database' => [
        'connection' => env('DUAL_AGENT_DB_CONNECTION', null), // Use default connection if null
        'cleanup' => [
            'enabled' => (bool) env('DUAL_AGENT_CLEANUP_ENABLED', true),
            'retention_days' => (int) env('DUAL_AGENT_RETENTION_DAYS', 30),
            'batch_size' => (int) env('DUAL_AGENT_CLEANUP_BATCH_SIZE', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure metric aggregation settings for performance dashboards.
    |
    */
    'aggregation' => [
        'enabled' => (bool) env('DUAL_AGENT_AGGREGATION_ENABLED', true),
        'schedule' => env('DUAL_AGENT_AGGREGATION_SCHEDULE', '0 * * * *'), // Every hour
        'batch_size' => (int) env('DUAL_AGENT_AGGREGATION_BATCH_SIZE', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance thresholds and monitoring settings.
    |
    */
    'performance' => [
        'slow_request_threshold' => (int) env('DUAL_AGENT_SLOW_REQUEST_THRESHOLD', 1000), // milliseconds
        'slow_query_threshold' => (int) env('DUAL_AGENT_SLOW_QUERY_THRESHOLD', 100), // milliseconds
        'memory_threshold' => (int) env('DUAL_AGENT_MEMORY_THRESHOLD', 128 * 1024 * 1024), // 128MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Version
    |--------------------------------------------------------------------------
    |
    | Package version for identification and debugging.
    |
    */
    'version' => '1.0.0',
];