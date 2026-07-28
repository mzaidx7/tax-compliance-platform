<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_rule_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->string('key', 100);
            $table->string('name', 150);
            $table->string('data_domain', 32);
            $table->string('field_or_scenario', 150);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'key']);
        });

        Schema::create('data_quality_rule_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('data_quality_rule_definition_id');
            $table->unsignedInteger('version');
            $table->string('status', 24);
            $table->text('applicability_criteria');
            $table->string('severity', 16);
            $table->string('behavior', 16);
            $table->text('explanation');
            $table->text('remediation_guidance');
            $table->string('source_kind', 16);
            $table->string('source_title');
            $table->string('source_url', 2000)->nullable();
            $table->string('formula_version_effect', 500);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('source_last_verified_on')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('change_summary', 500);
            $table->timestamps();
            $table->foreign(['firm_id', 'data_quality_rule_definition_id'])->references(['firm_id', 'id'])->on('data_quality_rule_definitions')->restrictOnDelete();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'data_quality_rule_definition_id', 'version'], 'data_quality_rule_version_identity');
        });

        Schema::create('data_quality_rule_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('data_quality_rule_version_id');
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->foreignId('acted_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'data_quality_rule_version_id'])->references(['firm_id', 'id'])->on('data_quality_rule_versions')->restrictOnDelete();
            $table->index(['firm_id', 'data_quality_rule_version_id', 'occurred_at'], 'data_quality_rule_event_lookup');
        });

        $this->guardTables();
    }

    public function down(): void
    {
        foreach (['data_quality_rule_definitions', 'data_quality_rule_events'] as $table) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }
        foreach (['no_delete', 'no_update', 'invalid_status'] as $suffix) {
            DB::unprepared("DROP TRIGGER IF EXISTS data_quality_rule_versions_{$suffix};");
        }
        Schema::dropIfExists('data_quality_rule_events');
        Schema::dropIfExists('data_quality_rule_versions');
        Schema::dropIfExists('data_quality_rule_definitions');
    }

    private function guardTables(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (['data_quality_rule_definitions', 'data_quality_rule_events'] as $table) {
            foreach (['update', 'delete'] as $event) {
                $name = "{$table}_no_{$event}";
                $message = 'Readiness rule identity and history are immutable.';
                if ($driver === 'sqlite') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
                } elseif ($driver === 'mysql') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END");
                }
            }
        }
        $valid = "(OLD.status = NEW.status) OR (OLD.status = 'draft' AND NEW.status = 'under_review') OR (OLD.status = 'under_review' AND NEW.status = 'approved' AND NEW.verified_by IS NOT NULL AND NEW.verified_at IS NOT NULL AND NEW.approved_at IS NOT NULL AND NEW.source_last_verified_on IS NOT NULL) OR (OLD.status = 'approved' AND NEW.status = 'published' AND NEW.published_at IS NOT NULL) OR (OLD.status = 'published' AND NEW.status IN ('superseded', 'retired'))";
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER data_quality_rule_versions_no_delete BEFORE DELETE ON data_quality_rule_versions BEGIN SELECT RAISE(ABORT, 'Readiness rule versions cannot be deleted.'); END;");
            DB::unprepared("CREATE TRIGGER data_quality_rule_versions_no_update BEFORE UPDATE ON data_quality_rule_versions WHEN OLD.status <> 'draft' AND (OLD.applicability_criteria IS NOT NEW.applicability_criteria OR OLD.severity IS NOT NEW.severity OR OLD.behavior IS NOT NEW.behavior OR OLD.explanation IS NOT NEW.explanation OR OLD.remediation_guidance IS NOT NEW.remediation_guidance OR OLD.source_kind IS NOT NEW.source_kind OR OLD.source_title IS NOT NEW.source_title OR OLD.source_url IS NOT NEW.source_url OR OLD.formula_version_effect IS NOT NEW.formula_version_effect OR OLD.change_summary IS NOT NEW.change_summary) BEGIN SELECT RAISE(ABORT, 'Readiness rule content is immutable after draft.'); END;");
            DB::unprepared("CREATE TRIGGER data_quality_rule_versions_invalid_status BEFORE UPDATE ON data_quality_rule_versions WHEN NOT ({$valid}) BEGIN SELECT RAISE(ABORT, 'Invalid readiness rule lifecycle transition.'); END;");
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER data_quality_rule_versions_no_delete BEFORE DELETE ON data_quality_rule_versions FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Readiness rule versions cannot be deleted.'; END");
            DB::unprepared("CREATE TRIGGER data_quality_rule_versions_no_update BEFORE UPDATE ON data_quality_rule_versions FOR EACH ROW BEGIN IF OLD.status <> 'draft' AND (NOT (OLD.applicability_criteria <=> NEW.applicability_criteria) OR NOT (OLD.severity <=> NEW.severity) OR NOT (OLD.behavior <=> NEW.behavior) OR NOT (OLD.explanation <=> NEW.explanation) OR NOT (OLD.remediation_guidance <=> NEW.remediation_guidance) OR NOT (OLD.source_kind <=> NEW.source_kind) OR NOT (OLD.source_title <=> NEW.source_title) OR NOT (OLD.source_url <=> NEW.source_url) OR NOT (OLD.formula_version_effect <=> NEW.formula_version_effect) OR NOT (OLD.change_summary <=> NEW.change_summary)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Readiness rule content is immutable after draft.'; END IF; END");
            DB::unprepared("CREATE TRIGGER data_quality_rule_versions_invalid_status BEFORE UPDATE ON data_quality_rule_versions FOR EACH ROW BEGIN IF NOT ({$valid}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid readiness rule lifecycle transition.'; END IF; END");
        }
    }
};
