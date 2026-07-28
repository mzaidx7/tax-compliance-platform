<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_issues', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->string('issue_key', 64);
            $table->ulid('party_record_id');
            $table->ulid('party_field_version_id')->nullable();
            $table->ulid('data_quality_rule_version_id');
            $table->string('severity_snapshot', 16);
            $table->string('behavior_snapshot', 16);
            $table->text('explanation_snapshot');
            $table->text('remediation_snapshot');
            $table->string('evidence_note', 1000);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'issue_key']);
            $table->foreign(['firm_id', 'party_record_id'])->references(['firm_id', 'id'])->on('party_records')->restrictOnDelete();
            $table->foreign(['firm_id', 'party_field_version_id'])->references(['firm_id', 'id'])->on('party_field_versions')->restrictOnDelete();
            $table->foreign(['firm_id', 'data_quality_rule_version_id'])->references(['firm_id', 'id'])->on('data_quality_rule_versions')->restrictOnDelete();
            $table->index(['firm_id', 'party_record_id', 'recorded_at'], 'party_issue_lookup');
        });

        Schema::create('party_issue_resolutions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('party_issue_id');
            $table->string('outcome', 24);
            $table->string('reason', 500);
            $table->foreignId('resolved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'party_issue_id'])->references(['firm_id', 'id'])->on('party_issues')->restrictOnDelete();
            $table->unique(['firm_id', 'party_issue_id']);
        });

        $this->guard('party_issues');
        $this->guard('party_issue_resolutions');
    }

    public function down(): void
    {
        foreach (['party_issues', 'party_issue_resolutions'] as $table) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }
        Schema::dropIfExists('party_issue_resolutions');
        Schema::dropIfExists('party_issues');
    }

    /** @param literal-string $table */
    private function guard(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (['update', 'delete'] as $event) {
            $name = "{$table}_no_{$event}";
            $message = 'Party issue evidence is append-only.';
            if ($driver === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
            } elseif ($driver === 'mysql') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END");
            }
        }
    }
};
