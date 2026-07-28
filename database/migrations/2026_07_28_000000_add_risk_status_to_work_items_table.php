<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const APPEND_ONLY_MESSAGE = 'Work item risk history is append-only.';

    /**
     * Run the migrations.
     *
     * Risk status is a fifth independent dimension on the work item, per the
     * master plan's work item field list. It never reads or writes work,
     * filing, payment or tax state.
     */
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->string('risk_status', 16)->default('unassessed')->after('status');
        });

        Schema::create('work_item_risk_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('work_item_id');
            $table->string('previous_risk_status', 16)->nullable();
            $table->string('new_risk_status', 16);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('changed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'work_item_id'])
                ->references(['firm_id', 'id'])
                ->on('work_items')
                ->restrictOnDelete();
            $table->index(['firm_id', 'work_item_id', 'changed_at']);
        });

        $this->guardAppendOnly();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['update', 'delete'] as $event) {
            DB::unprepared("DROP TRIGGER IF EXISTS work_item_risk_changes_no_{$event};");
        }

        Schema::dropIfExists('work_item_risk_changes');

        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn('risk_status');
        });
    }

    /**
     * Eloquent model events never fire for query-builder mass operations or raw
     * SQL, so history immutability is enforced at the database layer as well.
     */
    private function guardAppendOnly(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $message = self::APPEND_ONLY_MESSAGE;

        foreach (['update', 'delete'] as $event) {
            $triggerName = "work_item_risk_changes_no_{$event}";

            if ($driver === 'sqlite') {
                DB::unprepared(
                    "CREATE TRIGGER {$triggerName}
                     BEFORE {$event} ON work_item_risk_changes
                     BEGIN
                         SELECT RAISE(ABORT, '{$message}');
                     END;",
                );
            } elseif ($driver === 'mysql') {
                $upperEvent = strtoupper($event);
                DB::unprepared(
                    "CREATE TRIGGER {$triggerName}
                     BEFORE {$upperEvent} ON work_item_risk_changes
                     FOR EACH ROW
                     BEGIN
                         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                     END",
                );
            }
        }
    }
};
