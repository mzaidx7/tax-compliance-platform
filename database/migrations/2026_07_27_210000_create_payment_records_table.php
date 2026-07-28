<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Payment state is stored separately from work status and filing status so
     * one field can never express a contradictory combined state.
     */
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('obligation_id');
            $table->string('status', 32);
            $table->string('payment_reference', 100)->nullable();
            $table->date('paid_on')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['firm_id', 'obligation_id'])
                ->references(['firm_id', 'id'])
                ->on('obligations')
                ->restrictOnDelete();
            $table->unique(['firm_id', 'obligation_id']);
            $table->unique(['firm_id', 'id']);
            $table->index(['firm_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};
