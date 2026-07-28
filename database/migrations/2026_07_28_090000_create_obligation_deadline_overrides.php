<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligations', function (Blueprint $table) {
            $table->date('effective_due_date')->nullable()->after('statutory_due_date');
            $table->index(['firm_id', 'effective_due_date']);
        });

        DB::table('obligations')->update([
            'effective_due_date' => DB::raw('statutory_due_date'),
        ]);

        Schema::create('obligation_deadline_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('obligation_id');
            $table->date('previous_effective_due_date');
            $table->date('new_effective_due_date');
            $table->string('reason', 500);
            $table->foreignId('overridden_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('overridden_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'obligation_id'])
                ->references(['firm_id', 'id'])->on('obligations')->restrictOnDelete();
            $table->index(['firm_id', 'obligation_id', 'overridden_at']);
        });

        $this->guardHistory();
    }

    public function down(): void
    {
        foreach (['update', 'delete'] as $event) {
            DB::unprepared("DROP TRIGGER IF EXISTS obligation_deadline_overrides_no_{$event};");
        }

        Schema::dropIfExists('obligation_deadline_overrides');

        Schema::table('obligations', function (Blueprint $table) {
            $table->dropIndex(['firm_id', 'effective_due_date']);
            $table->dropColumn('effective_due_date');
        });
    }

    private function guardHistory(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (['update', 'delete'] as $event) {
            $triggerName = "obligation_deadline_overrides_no_{$event}";
            $message = 'Obligation deadline override history is append-only.';

            if ($driver === 'sqlite') {
                DB::unprepared(
                    "CREATE TRIGGER {$triggerName}
                     BEFORE {$event} ON obligation_deadline_overrides
                     BEGIN
                         SELECT RAISE(ABORT, '{$message}');
                     END;",
                );
            } elseif ($driver === 'mysql') {
                $upperEvent = strtoupper($event);
                DB::unprepared(
                    "CREATE TRIGGER {$triggerName}
                     BEFORE {$upperEvent} ON obligation_deadline_overrides
                     FOR EACH ROW
                     BEGIN
                         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                     END",
                );
            }
        }
    }
};
