<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dual_agent_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->timestamp('event_timestamp')->index();
            $table->string('trace_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            
            // Request metrics
            $table->string('method')->nullable();
            $table->text('url')->nullable();
            $table->string('route_name')->nullable();
            $table->text('route_path')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->decimal('duration', 10, 3)->nullable()->index(); // milliseconds
            $table->unsignedInteger('memory_usage')->nullable();
            $table->unsignedInteger('request_size')->nullable();
            $table->unsignedInteger('response_size')->nullable();
            
            // Database metrics
            $table->text('sql')->nullable();
            $table->string('connection')->nullable();
            $table->decimal('query_duration', 10, 3)->nullable();
            $table->json('bindings')->nullable();
            
            // Exception metrics
            $table->string('exception_class')->nullable()->index();
            $table->text('exception_message')->nullable();
            $table->string('exception_file')->nullable();
            $table->unsignedInteger('exception_line')->nullable();
            $table->json('exception_trace')->nullable();
            
            // Job metrics
            $table->string('job_class')->nullable()->index();
            $table->string('queue')->nullable()->index();
            $table->string('job_status')->nullable(); // queued, processing, completed, failed
            $table->unsignedInteger('attempts')->nullable();
            $table->decimal('job_duration', 10, 3)->nullable();
            
            // Cache metrics
            $table->string('cache_key')->nullable();
            $table->string('cache_operation')->nullable(); // hit, miss, write, delete
            $table->string('cache_store')->nullable();
            
            // Mail metrics
            $table->string('mail_class')->nullable();
            $table->json('mail_to')->nullable();
            $table->string('mail_subject')->nullable();
            
            // Log metrics
            $table->string('log_level')->nullable()->index();
            $table->text('log_message')->nullable();
            $table->json('log_context')->nullable();
            
            // Performance stages
            $table->decimal('bootstrap_duration', 10, 3)->nullable();
            $table->decimal('before_middleware_duration', 10, 3)->nullable();
            $table->decimal('action_duration', 10, 3)->nullable();
            $table->decimal('render_duration', 10, 3)->nullable();
            $table->decimal('after_middleware_duration', 10, 3)->nullable();
            $table->decimal('terminating_duration', 10, 3)->nullable();
            
            // Environment info
            $table->string('environment')->nullable()->index();
            $table->string('server_name')->nullable();
            $table->string('app_version')->nullable();
            
            // Additional metadata
            $table->json('custom_metadata')->nullable();
            $table->json('raw_payload')->nullable();
            
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['event_type', 'event_timestamp']);
            $table->index(['user_id', 'event_timestamp']);
            $table->index(['status_code', 'event_timestamp']);
            $table->index(['exception_class', 'event_timestamp']);
            $table->index(['job_status', 'event_timestamp']);
            $table->index(['environment', 'event_timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dual_agent_metrics');
    }
};