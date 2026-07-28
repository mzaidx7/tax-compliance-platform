<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarded tables and their append-only violation message.
     *
     * @var array<string, string>
     */
    private const GUARDED_TABLES = [
        'work_item_transitions' => 'Work item transition history is append-only.',
        'audit_logs' => 'Audit records are append-only.',
    ];

    /**
     * Run the migrations.
     *
     * Eloquent model events already reject updates and deletes on these tables,
     * but model events never fire for query-builder mass operations or raw SQL.
     * These triggers enforce the same append-only guarantee at the database
     * layer so history cannot be altered outside the application either.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (self::GUARDED_TABLES as $table => $message) {
            foreach (['update', 'delete'] as $event) {
                $triggerName = "{$table}_no_{$event}";

                if ($driver === 'sqlite') {
                    DB::unprepared(
                        "CREATE TRIGGER {$triggerName}
                         BEFORE {$event} ON {$table}
                         BEGIN
                             SELECT RAISE(ABORT, '{$message}');
                         END;",
                    );
                } elseif ($driver === 'mysql') {
                    $upperEvent = strtoupper($event);
                    DB::unprepared(
                        "CREATE TRIGGER {$triggerName}
                         BEFORE {$upperEvent} ON {$table}
                         FOR EACH ROW
                         BEGIN
                             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                         END",
                    );
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::GUARDED_TABLES as $table => $message) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }
    }
};
