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
        Schema::create('checklist_item_completions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('work_item_checklist_id');
            $table->ulid('checklist_item_id');
            $table->foreignId('completed_by')->constrained('users')->restrictOnDelete();
            $table->string('evidence_note', 500);
            $table->timestamp('completed_at');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'work_item_checklist_id'])
                ->references(['firm_id', 'id'])->on('work_item_checklists')->restrictOnDelete();
            $table->foreign(['firm_id', 'checklist_item_id'])
                ->references(['firm_id', 'id'])->on('checklist_items')->restrictOnDelete();
            $table->unique(['firm_id', 'work_item_checklist_id', 'checklist_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_item_completions');
    }
};
