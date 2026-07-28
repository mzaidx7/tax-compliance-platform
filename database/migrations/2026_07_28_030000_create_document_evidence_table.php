<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_evidence', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('work_item_id');
            $table->string('purpose', 32);
            $table->string('original_name', 255);
            $table->string('extension', 8);
            $table->string('detected_mime_type', 100);
            $table->string('logical_path', 300);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('bytes');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'work_item_id'])
                ->references(['firm_id', 'id'])
                ->on('work_items')
                ->restrictOnDelete();
            $table->index(['firm_id', 'work_item_id', 'uploaded_at']);
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'logical_path']);
        });

        $this->guardAppendOnly();
    }

    public function down(): void
    {
        foreach (['update', 'delete'] as $event) {
            DB::unprepared("DROP TRIGGER IF EXISTS document_evidence_no_{$event};");
        }

        Schema::dropIfExists('document_evidence');
    }

    private function guardAppendOnly(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $message = 'Document evidence is append-only.';

        foreach (['update', 'delete'] as $event) {
            $trigger = "document_evidence_no_{$event}";

            if ($driver === 'sqlite') {
                DB::unprepared(
                    "CREATE TRIGGER {$trigger}
                     BEFORE {$event} ON document_evidence
                     BEGIN
                         SELECT RAISE(ABORT, '{$message}');
                     END;",
                );
            } elseif ($driver === 'mysql') {
                DB::unprepared(
                    "CREATE TRIGGER {$trigger}
                     BEFORE ".strtoupper($event).' ON document_evidence
                     FOR EACH ROW
                     BEGIN
                         SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \''.$message."';
                     END",
                );
            }
        }
    }
};
