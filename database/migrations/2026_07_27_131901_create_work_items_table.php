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
        Schema::table('obligations', function (Blueprint $table) {
            $table->unique(['firm_id', 'id']);
        });

        Schema::table('firm_users', function (Blueprint $table) {
            $table->unique(['firm_id', 'id']);
        });

        Schema::create('work_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('obligation_id');
            $table->string('status', 32);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['firm_id', 'obligation_id'])
                ->references(['firm_id', 'id'])
                ->on('obligations')
                ->restrictOnDelete();
            $table->unique(['firm_id', 'obligation_id']);
            $table->unique(['firm_id', 'id']);
            $table->index(['firm_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_items');

        Schema::table('firm_users', function (Blueprint $table) {
            $table->dropUnique(['firm_id', 'id']);
        });

        Schema::table('obligations', function (Blueprint $table) {
            $table->dropUnique(['firm_id', 'id']);
        });
    }
};
