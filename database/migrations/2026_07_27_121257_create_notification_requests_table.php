<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('firm_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('template_key', 100);
            $table->unsignedSmallInteger('template_version');
            $table->string('channel', 32);
            $table->char('deterministic_key', 64);
            $table->string('trigger_type')->nullable();
            $table->string('trigger_id')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('status', 32);
            $table->string('final_status', 32)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->ulid('correlation_id')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['firm_id', 'deterministic_key']);
            $table->unique(['firm_id', 'id']);
            $table->index(['firm_id', 'recipient_user_id', 'status']);
            $table->index(['firm_id', 'scheduled_at']);
            $table->index(['trigger_type', 'trigger_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
