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
        Schema::create('assignment_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('work_item_id');
            $table->string('assignment_role', 32);
            $table->ulid('assigned_membership_id');
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('assigned_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'work_item_id'])
                ->references(['firm_id', 'id'])
                ->on('work_items')
                ->restrictOnDelete();
            $table->foreign(['firm_id', 'assigned_membership_id'])
                ->references(['firm_id', 'id'])
                ->on('firm_users')
                ->restrictOnDelete();
            $table->index(['firm_id', 'work_item_id', 'assignment_role', 'assigned_at']);
            $table->index(['firm_id', 'assigned_membership_id', 'assigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_histories');
    }
};
