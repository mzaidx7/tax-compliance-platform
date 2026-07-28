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
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('workflow_definition_id');
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('assignment_role', 32);
            $table->unsignedSmallInteger('position');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'workflow_definition_id'])
                ->references(['firm_id', 'id'])
                ->on('workflow_definitions')
                ->restrictOnDelete();
            $table->unique(['firm_id', 'workflow_definition_id', 'from_status', 'to_status']);
            $table->index(['firm_id', 'workflow_definition_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
