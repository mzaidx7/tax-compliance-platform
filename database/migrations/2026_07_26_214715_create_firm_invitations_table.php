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
        Schema::create('firm_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('firm_id')->constrained()->restrictOnDelete();
            $table->string('email');
            $table->string('role', 64);
            $table->string('status', 32);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['firm_id', 'email', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firm_invitations');
    }
};
