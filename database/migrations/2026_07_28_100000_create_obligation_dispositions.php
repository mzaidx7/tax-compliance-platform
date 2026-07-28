<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_dispositions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('obligation_id');
            $table->string('previous_status', 24);
            $table->string('new_status', 24);
            $table->ulid('replacement_obligation_id')->nullable();
            $table->string('reason', 500);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'obligation_id'])->references(['firm_id', 'id'])->on('obligations')->restrictOnDelete();
            $table->foreign(['firm_id', 'replacement_obligation_id'])->references(['firm_id', 'id'])->on('obligations')->restrictOnDelete();
            $table->index(['firm_id', 'obligation_id', 'recorded_at']);
        });

        $this->guardHistory();
    }

    public function down(): void
    {
        foreach (['update', 'delete'] as $event) {
            DB::unprepared("DROP TRIGGER IF EXISTS obligation_dispositions_no_{$event};");
        }
        Schema::dropIfExists('obligation_dispositions');
    }

    private function guardHistory(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (['update', 'delete'] as $event) {
            $name = "obligation_dispositions_no_{$event}";
            $message = 'Obligation disposition history is append-only.';
            if ($driver === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON obligation_dispositions BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
            } elseif ($driver === 'mysql') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON obligation_dispositions FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END");
            }
        }
    }
};
