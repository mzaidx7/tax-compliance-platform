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
        Schema::table('clients', function (Blueprint $table) {
            $table->unique(['firm_id', 'id']);
        });

        Schema::create('obligations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->string('obligation_type', 100);
            $table->string('period_label', 100)->nullable();
            $table->date('statutory_due_date');
            $table->date('internal_target_date')->nullable();
            $table->string('origin', 32);
            $table->string('status', 32);
            $table->text('source_reference');
            $table->date('last_verified_on');
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['firm_id', 'client_id'])
                ->references(['firm_id', 'id'])
                ->on('clients')
                ->restrictOnDelete();
            $table->index(['firm_id', 'statutory_due_date']);
            $table->index(['firm_id', 'status', 'statutory_due_date']);
            $table->index(['firm_id', 'client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obligations');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['firm_id', 'id']);
        });
    }
};
