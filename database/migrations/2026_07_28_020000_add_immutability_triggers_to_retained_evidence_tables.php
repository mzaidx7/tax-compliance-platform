<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const GUARDED_TABLES = [
        'assignment_histories' => 'Assignment history is append-only.',
        'checklist_item_completions' => 'Checklist completion evidence is append-only.',
    ];

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

    public function down(): void
    {
        foreach (array_keys(self::GUARDED_TABLES) as $table) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }
    }
};
