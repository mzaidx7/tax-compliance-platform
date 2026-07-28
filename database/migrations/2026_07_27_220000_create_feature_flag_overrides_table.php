<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A firm-scoped override records a firm's deliberate decision to enable or
     * disable one feature. Configuration remains the fallback until a firm has
     * recorded an override. This is current-state; every change is captured in
     * append-only audit history.
     */
    public function up(): void
    {
        Schema::create('feature_flag_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->string('feature', 64);
            $table->boolean('enabled');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('firm_id')->references('id')->on('firms')->restrictOnDelete();
            $table->unique(['firm_id', 'feature']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_flag_overrides');
    }
};
