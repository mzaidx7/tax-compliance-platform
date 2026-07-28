<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const IMMUTABLE_TABLES = ['party_records', 'party_field_versions', 'party_correction_proposals', 'party_correction_decisions'];

    public function up(): void
    {
        Schema::create('party_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->string('reference', 150);
            $table->boolean('is_customer');
            $table->boolean('is_supplier');
            $table->boolean('is_active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'id']);
            $table->foreign(['firm_id', 'client_id'])->references(['firm_id', 'id'])->on('clients')->restrictOnDelete();
            $table->unique(['firm_id', 'client_id', 'reference']);
        });

        Schema::create('party_field_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('party_record_id');
            $table->string('field_key', 40);
            $table->text('value');
            $table->string('verification_state', 24);
            $table->string('source_kind', 24);
            $table->string('source_reference', 500);
            $table->ulid('supersedes_party_field_version_id')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'id']);
            $table->foreign(['firm_id', 'party_record_id'])->references(['firm_id', 'id'])->on('party_records')->restrictOnDelete();
            $table->foreign(['firm_id', 'supersedes_party_field_version_id'])->references(['firm_id', 'id'])->on('party_field_versions')->restrictOnDelete();
            $table->index(['firm_id', 'party_record_id', 'field_key', 'recorded_at'], 'party_field_current_lookup');
        });

        Schema::create('party_correction_proposals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('party_record_id');
            $table->ulid('current_party_field_version_id');
            $table->text('proposed_value');
            $table->string('evidence_note', 1000);
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('proposed_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'id']);
            $table->foreign(['firm_id', 'party_record_id'])->references(['firm_id', 'id'])->on('party_records')->restrictOnDelete();
            $table->foreign(['firm_id', 'current_party_field_version_id'])->references(['firm_id', 'id'])->on('party_field_versions')->restrictOnDelete();
            $table->index(['firm_id', 'party_record_id', 'proposed_at'], 'party_correction_proposal_lookup');
        });

        Schema::create('party_correction_decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('party_correction_proposal_id');
            $table->string('decision', 16);
            $table->ulid('new_party_field_version_id')->nullable();
            $table->string('reason', 500);
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'party_correction_proposal_id'])->references(['firm_id', 'id'])->on('party_correction_proposals')->restrictOnDelete();
            $table->foreign(['firm_id', 'new_party_field_version_id'])->references(['firm_id', 'id'])->on('party_field_versions')->restrictOnDelete();
            $table->unique(['firm_id', 'party_correction_proposal_id']);
        });

        $this->guardEvidence();
    }

    public function down(): void
    {
        foreach (self::IMMUTABLE_TABLES as $table) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }
        Schema::dropIfExists('party_correction_decisions');
        Schema::dropIfExists('party_correction_proposals');
        Schema::dropIfExists('party_field_versions');
        Schema::dropIfExists('party_records');
    }

    private function guardEvidence(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (self::IMMUTABLE_TABLES as $table) {
            foreach (['update', 'delete'] as $event) {
                $name = "{$table}_no_{$event}";
                $message = 'Party master evidence is append-only.';
                if ($driver === 'sqlite') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
                } elseif ($driver === 'mysql') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END");
                }
            }
        }
    }
};
