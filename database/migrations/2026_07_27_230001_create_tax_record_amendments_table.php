<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const APPEND_ONLY_MESSAGE = 'Tax record amendment history is append-only.';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_record_amendments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('tax_record_id');
            $table->string('previous_status', 16)->nullable();
            $table->decimal('previous_taxable_amount', 15, 2)->nullable();
            $table->decimal('previous_tax_amount', 15, 2)->nullable();
            $table->string('new_status', 16);
            $table->decimal('new_taxable_amount', 15, 2);
            $table->decimal('new_tax_amount', 15, 2);
            $table->foreignId('amended_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('amended_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'tax_record_id'])
                ->references(['firm_id', 'id'])
                ->on('tax_records')
                ->restrictOnDelete();
            $table->index(['firm_id', 'tax_record_id', 'amended_at']);
        });

        $this->guardAppendOnly();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['update', 'delete'] as $event) {
            DB::unprepared("DROP TRIGGER IF EXISTS tax_record_amendments_no_{$event};");
        }

        Schema::dropIfExists('tax_record_amendments');
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
            $triggerName = "tax_record_amendments_no_{$event}";

            if ($driver === 'sqlite') {
                DB::unprepared(
                    "CREATE TRIGGER {$triggerName}
                     BEFORE {$event} ON tax_record_amendments
                     BEGIN
                         SELECT RAISE(ABORT, '{$message}');
                     END;",
                );
            } elseif ($driver === 'mysql') {
                $upperEvent = strtoupper($event);
                DB::unprepared(
                    "CREATE TRIGGER {$triggerName}
                     BEFORE {$upperEvent} ON tax_record_amendments
                     FOR EACH ROW
                     BEGIN
                         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                     END",
                );
            }
        }
    }
};
