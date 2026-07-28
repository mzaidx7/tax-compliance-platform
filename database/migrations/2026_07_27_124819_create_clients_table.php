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
        Schema::create('clients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('firm_id')->constrained()->restrictOnDelete();
            $table->string('internal_code', 64);
            $table->string('internal_code_normalized', 64);
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('entity_type', 100)->nullable();
            $table->string('status', 32)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['firm_id', 'internal_code_normalized']);
            $table->index(['firm_id', 'status', 'legal_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
