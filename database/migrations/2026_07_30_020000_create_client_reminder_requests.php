<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_reminder_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->string('category', 32);
            $table->string('status', 32);
            $table->string('source_type');
            $table->string('source_id');
            $table->date('event_date');
            $table->smallInteger('days_before');
            $table->date('scheduled_for');
            $table->char('deterministic_key', 64);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamps();

            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'deterministic_key']);
            $table->foreign(['firm_id', 'client_id'])
                ->references(['firm_id', 'id'])
                ->on('clients')
                ->restrictOnDelete();
            $table->index(['firm_id', 'status', 'scheduled_for']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('client_reminder_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_reminder_request_id');
            $table->string('status', 16);
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'client_reminder_request_id'])
                ->references(['firm_id', 'id'])
                ->on('client_reminder_requests')
                ->restrictOnDelete();
            $table->index(['firm_id', 'client_reminder_request_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_reminder_attempts');
        Schema::dropIfExists('client_reminder_requests');
    }
};
