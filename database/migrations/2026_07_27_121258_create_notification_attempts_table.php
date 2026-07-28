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
        Schema::create('notification_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('notification_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 32);
            $table->string('provider_reference')->nullable();
            $table->string('failure_reason', 128)->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'notification_id'])
                ->references(['firm_id', 'id'])
                ->on('notifications')
                ->restrictOnDelete();
            $table->unique(['notification_id', 'attempt_number']);
            $table->index(['firm_id', 'status', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
    }
};
