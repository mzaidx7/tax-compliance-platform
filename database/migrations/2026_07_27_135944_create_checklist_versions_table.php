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
        Schema::create('checklist_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('checklist_template_id');
            $table->unsignedInteger('version');
            $table->string('status', 24);
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'checklist_template_id'])
                ->references(['firm_id', 'id'])->on('checklist_templates')->restrictOnDelete();
            $table->unique(['firm_id', 'checklist_template_id', 'version']);
            $table->unique(['firm_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_versions');
    }
};
