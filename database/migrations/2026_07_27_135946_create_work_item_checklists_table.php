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
        Schema::create('work_item_checklists', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('work_item_id');
            $table->ulid('checklist_version_id');
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('applied_at');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'work_item_id'])
                ->references(['firm_id', 'id'])->on('work_items')->restrictOnDelete();
            $table->foreign(['firm_id', 'checklist_version_id'])
                ->references(['firm_id', 'id'])->on('checklist_versions')->restrictOnDelete();
            $table->unique(['firm_id', 'work_item_id']);
            $table->unique(['firm_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_item_checklists');
    }
};
