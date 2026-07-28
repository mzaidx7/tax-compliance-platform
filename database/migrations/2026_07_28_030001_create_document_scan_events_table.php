<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_scan_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('document_evidence_id');
            $table->string('verdict', 24);
            $table->string('scanner', 100);
            $table->timestamp('scanned_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'document_evidence_id'])
                ->references(['firm_id', 'id'])
                ->on('document_evidence')
                ->restrictOnDelete();
            $table->index(['firm_id', 'document_evidence_id', 'scanned_at']);
        });

        $this->guardAppendOnly();
    }

    public function down(): void
    {
        foreach (['update', 'delete'] as $event) {
            DB::unprepared("DROP TRIGGER IF EXISTS document_scan_events_no_{$event};");
        }

        Schema::dropIfExists('document_scan_events');
    }

    private function guardAppendOnly(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $message = 'Document scan history is append-only.';

        foreach (['update', 'delete'] as $event) {
            $trigger = "document_scan_events_no_{$event}";

            if ($driver === 'sqlite') {
                DB::unprepared(
                    "CREATE TRIGGER {$trigger}
                     BEFORE {$event} ON document_scan_events
                     BEGIN
                         SELECT RAISE(ABORT, '{$message}');
                     END;",
                );
            } elseif ($driver === 'mysql') {
                DB::unprepared(
                    "CREATE TRIGGER {$trigger}
                     BEFORE ".strtoupper($event).' ON document_scan_events
                     FOR EACH ROW
                     BEGIN
                         SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \''.$message."';
                     END",
                );
            }
        }
    }
};
