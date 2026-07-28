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
        Schema::create('work_item_transitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('work_item_id');
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->foreignId('transitioned_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('transitioned_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'work_item_id'])
                ->references(['firm_id', 'id'])
                ->on('work_items')
                ->restrictOnDelete();
            $table->index(['firm_id', 'work_item_id', 'transitioned_at']);
            $table->index(['firm_id', 'to_status', 'transitioned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_item_transitions');
    }
};
