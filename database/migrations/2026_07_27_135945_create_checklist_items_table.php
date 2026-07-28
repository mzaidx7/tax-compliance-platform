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
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('checklist_version_id');
            $table->string('item_key', 80);
            $table->string('label', 255);
            $table->unsignedSmallInteger('position');
            $table->boolean('required')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'checklist_version_id'])
                ->references(['firm_id', 'id'])->on('checklist_versions')->restrictOnDelete();
            $table->unique(['firm_id', 'checklist_version_id', 'item_key']);
            $table->unique(['firm_id', 'checklist_version_id', 'position']);
            $table->unique(['firm_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
