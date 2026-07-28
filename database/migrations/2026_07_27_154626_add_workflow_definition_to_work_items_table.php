<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->ulid('workflow_definition_id')->nullable()->after('obligation_id');
        });

        $steps = [
            ['not_started', 'documents_requested', 'preparer'],
            ['not_started', 'ready_for_preparation', 'preparer'],
            ['not_started', 'in_preparation', 'preparer'],
            ['not_started', 'cancelled', 'responsible_manager'],
            ['documents_requested', 'awaiting_client', 'preparer'],
            ['documents_requested', 'ready_for_preparation', 'preparer'],
            ['documents_requested', 'cancelled', 'responsible_manager'],
            ['awaiting_client', 'ready_for_preparation', 'preparer'],
            ['awaiting_client', 'cancelled', 'responsible_manager'],
            ['ready_for_preparation', 'in_preparation', 'preparer'],
            ['ready_for_preparation', 'cancelled', 'responsible_manager'],
            ['in_preparation', 'under_review', 'preparer'],
            ['in_preparation', 'cancelled', 'responsible_manager'],
            ['under_review', 'returned_for_changes', 'reviewer'],
            ['under_review', 'awaiting_client_approval', 'reviewer'],
            ['under_review', 'ready_to_file', 'reviewer'],
            ['under_review', 'cancelled', 'responsible_manager'],
            ['returned_for_changes', 'in_preparation', 'preparer'],
            ['returned_for_changes', 'cancelled', 'responsible_manager'],
            ['awaiting_client_approval', 'ready_to_file', 'reviewer'],
            ['awaiting_client_approval', 'cancelled', 'responsible_manager'],
            ['ready_to_file', 'completed', 'responsible_manager'],
            ['ready_to_file', 'cancelled', 'responsible_manager'],
        ];
        $now = now('UTC');
        $legacyWork = DB::table('work_items')
            ->select('firm_id', DB::raw('MIN(created_by) as published_by'))
            ->groupBy('firm_id')
            ->get();

        foreach ($legacyWork as $firmWork) {
            $definitionId = (string) Str::ulid();
            DB::table('workflow_definitions')->insert([
                'id' => $definitionId,
                'firm_id' => $firmWork->firm_id,
                'definition_key' => 'core-compliance-workflow',
                'name' => 'Legacy core compliance workflow',
                'version' => 1,
                'status' => 'published',
                'published_by' => $firmWork->published_by,
                'published_at' => $now,
                'created_at' => $now,
            ]);

            foreach ($steps as $position => [$fromStatus, $toStatus, $assignmentRole]) {
                DB::table('workflow_steps')->insert([
                    'id' => (string) Str::ulid(),
                    'firm_id' => $firmWork->firm_id,
                    'workflow_definition_id' => $definitionId,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'assignment_role' => $assignmentRole,
                    'position' => $position + 1,
                    'created_at' => $now,
                ]);
            }

            DB::table('work_items')
                ->where('firm_id', $firmWork->firm_id)
                ->update(['workflow_definition_id' => $definitionId]);
        }

        Schema::table('work_items', function (Blueprint $table) {
            $table->ulid('workflow_definition_id')->nullable(false)->change();
            $table->foreign(['firm_id', 'workflow_definition_id'])
                ->references(['firm_id', 'id'])
                ->on('workflow_definitions')
                ->restrictOnDelete();
            $table->index(['firm_id', 'workflow_definition_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropForeign(['firm_id', 'workflow_definition_id']);
            $table->dropIndex(['firm_id', 'workflow_definition_id', 'status']);
            $table->dropColumn('workflow_definition_id');
        });
    }
};
