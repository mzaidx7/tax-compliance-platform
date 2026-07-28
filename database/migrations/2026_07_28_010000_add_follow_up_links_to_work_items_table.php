<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An obligation keeps exactly one primary work item. A controlled reopen
     * may add linked follow-up work items without changing the original.
     *
     * Primary uniqueness is expressed through a nullable marker column rather
     * than a partial index, because partial indexes are not portable to MySQL.
     * A primary work item stores its obligation identifier in the marker; a
     * follow-up stores null. Unique indexes treat nulls as distinct on both
     * supported drivers, so exactly one primary is enforced while any number of
     * follow-ups remain allowed.
     */
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->ulid('parent_work_item_id')->nullable()->after('obligation_id');
            $table->ulid('primary_obligation_id')->nullable()->after('parent_work_item_id');
        });

        DB::table('work_items')->update([
            'primary_obligation_id' => DB::raw('obligation_id'),
        ]);

        Schema::table('work_items', function (Blueprint $table) {
            $table->dropUnique(['firm_id', 'obligation_id']);
            $table->unique(['firm_id', 'primary_obligation_id']);
            $table->foreign(['firm_id', 'parent_work_item_id'])
                ->references(['firm_id', 'id'])
                ->on('work_items')
                ->restrictOnDelete();
            $table->index(['firm_id', 'parent_work_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('work_items')->whereNotNull('parent_work_item_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back controlled reopen while linked follow-up work exists. Preserve the records and use a forward recovery migration.',
            );
        }

        Schema::table('work_items', function (Blueprint $table) {
            $table->dropForeign(['firm_id', 'parent_work_item_id']);
            $table->dropIndex(['firm_id', 'parent_work_item_id']);
            $table->dropUnique(['firm_id', 'primary_obligation_id']);
            $table->dropColumn(['parent_work_item_id', 'primary_obligation_id']);
            $table->unique(['firm_id', 'obligation_id']);
        });
    }
};
