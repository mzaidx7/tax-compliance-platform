<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const APPEND_ONLY_MESSAGE = 'Payment record transition history is append-only.';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_record_transitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('payment_record_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('transitioned_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('transitioned_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'payment_record_id'])
                ->references(['firm_id', 'id'])
                ->on('payment_records')
                ->restrictOnDelete();
            $table->index(['firm_id', 'payment_record_id', 'transitioned_at']);
            $table->index(['firm_id', 'to_status', 'transitioned_at']);
        });

        $this->guardAppendOnly();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['update', 'delete'] as $event) {
            DB::unprepared("DROP TRIGGER IF EXISTS payment_record_transitions_no_{$event};");
        }

        Schema::dropIfExists('payment_record_transitions');
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
            $triggerName = "payment_record_transitions_no_{$event}";

            if ($driver === 'sqlite') {
                DB::unprepared(
                    "CREATE TRIGGER {$triggerName}
                     BEFORE {$event} ON payment_record_transitions
                     BEGIN
                         SELECT RAISE(ABORT, '{$message}');
                     END;",
                );
            } elseif ($driver === 'mysql') {
                $upperEvent = strtoupper($event);
                DB::unprepared(
                    "CREATE TRIGGER {$triggerName}
                     BEFORE {$upperEvent} ON payment_record_transitions
                     FOR EACH ROW
                     BEGIN
                         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                     END",
                );
            }
        }
    }
};
