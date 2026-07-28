<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = ['invoice_readiness_samples', 'invoice_sample_fields', 'invoice_readiness_issues', 'invoice_issue_resolutions'];

    public function up(): void
    {
        Schema::create('invoice_readiness_samples', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->string('sample_reference', 150);
            $table->string('source_reference', 500);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'client_id', 'sample_reference']);
            $table->foreign(['firm_id', 'client_id'])->references(['firm_id', 'id'])->on('clients')->restrictOnDelete();
        });

        Schema::create('invoice_sample_fields', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('invoice_readiness_sample_id');
            $table->string('field_key', 48);
            $table->text('supplied_value');
            $table->string('source_reference', 500);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'invoice_readiness_sample_id', 'field_key'], 'invoice_sample_field_unique');
            $table->foreign(['firm_id', 'invoice_readiness_sample_id'])->references(['firm_id', 'id'])->on('invoice_readiness_samples')->restrictOnDelete();
        });

        Schema::create('invoice_readiness_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->char('issue_key', 64);
            $table->ulid('invoice_readiness_sample_id');
            $table->ulid('invoice_sample_field_id')->nullable();
            $table->ulid('data_quality_rule_version_id');
            $table->string('severity_snapshot', 16);
            $table->string('behavior_snapshot', 16);
            $table->string('explanation_snapshot', 1000);
            $table->string('remediation_snapshot', 1000);
            $table->string('evidence_note', 1000);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'issue_key']);
            $table->foreign(['firm_id', 'invoice_readiness_sample_id'])->references(['firm_id', 'id'])->on('invoice_readiness_samples')->restrictOnDelete();
            $table->foreign(['firm_id', 'invoice_sample_field_id'])->references(['firm_id', 'id'])->on('invoice_sample_fields')->restrictOnDelete();
            $table->foreign(['firm_id', 'data_quality_rule_version_id'])->references(['firm_id', 'id'])->on('data_quality_rule_versions')->restrictOnDelete();
        });

        Schema::create('invoice_issue_resolutions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('invoice_readiness_issue_id');
            $table->string('outcome', 24);
            $table->string('reason', 500);
            $table->foreignId('resolved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'invoice_readiness_issue_id']);
            $table->foreign(['firm_id', 'invoice_readiness_issue_id'])->references(['firm_id', 'id'])->on('invoice_readiness_issues')->restrictOnDelete();
        });

        foreach (self::TABLES as $table) {
            $this->guard($table);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            if (DB::getDriverName() === 'mysql') {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_prevent_update");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_prevent_delete");
            }
            Schema::dropIfExists($table);
        }
    }

    /** @param literal-string $table */
    private function guard(string $table): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER {$table}_prevent_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table} is append-only'); END;");
            DB::unprepared("CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, '{$table} is append-only'); END;");
        }
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER {$table}_prevent_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} is append-only'");
            DB::unprepared("CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} is append-only'");
        }
    }
};
