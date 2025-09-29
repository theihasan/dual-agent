<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dual_agent_aggregated_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type')->index(); // hourly, daily, weekly
            $table->string('event_type')->index();
            $table->date('metric_date')->index();
            $table->unsignedTinyInteger('metric_hour')->nullable()->index(); // 0-23 for hourly metrics
            
            // Count metrics
            $table->unsignedBigInteger('total_events')->default(0);
            $table->unsignedBigInteger('unique_users')->default(0);
            $table->unsignedBigInteger('unique_sessions')->default(0);
            
            // Performance metrics
            $table->decimal('avg_duration', 10, 3)->nullable();
            $table->decimal('min_duration', 10, 3)->nullable();
            $table->decimal('max_duration', 10, 3)->nullable();
            $table->decimal('p95_duration', 10, 3)->nullable();
            $table->decimal('p99_duration', 10, 3)->nullable();
            
            // HTTP status codes (for request events)
            $table->unsignedInteger('status_2xx')->default(0);
            $table->unsignedInteger('status_3xx')->default(0);
            $table->unsignedInteger('status_4xx')->default(0);
            $table->unsignedInteger('status_5xx')->default(0);
            
            // Database metrics (for query events)
            $table->unsignedBigInteger('total_queries')->default(0);
            $table->decimal('avg_query_duration', 10, 3)->nullable();
            
            // Exception metrics
            $table->unsignedBigInteger('total_exceptions')->default(0);
            $table->json('top_exceptions')->nullable(); // Array of exception classes with counts
            
            // Job metrics
            $table->unsignedInteger('jobs_queued')->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->unsignedInteger('jobs_failed')->default(0);
            
            // Memory usage
            $table->unsignedBigInteger('avg_memory_usage')->nullable();
            $table->unsignedBigInteger('peak_memory_usage')->nullable();
            
            $table->timestamps();
            
            // Compound indexes for efficient querying
            $table->unique(['metric_type', 'event_type', 'metric_date', 'metric_hour'], 'unique_metric');
            $table->index(['event_type', 'metric_date']);
            $table->index(['metric_date', 'metric_hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dual_agent_aggregated_metrics');
    }
};