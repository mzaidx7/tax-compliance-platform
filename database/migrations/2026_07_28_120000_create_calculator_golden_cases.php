<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculator_golden_case_sets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->string('calculator_key', 100);
            $table->unsignedInteger('version');
            $table->string('name', 150);
            $table->string('status', 16)->default('draft');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'calculator_key', 'version'], 'calculator_case_set_version');
        });

        Schema::create('calculator_golden_cases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('calculator_golden_case_set_id');
            $table->string('name', 150);
            $table->json('input_snapshot');
            $table->json('parameter_snapshot');
            $table->json('expected_result_snapshot');
            $table->string('official_source_title');
            $table->string('official_source_url', 2000);
            $table->date('source_verified_on');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'calculator_golden_case_set_id'])->references(['firm_id', 'id'])->on('calculator_golden_case_sets')->restrictOnDelete();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'calculator_golden_case_set_id', 'name'], 'calculator_case_name');
        });

        Schema::create('calculator_golden_case_verifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('calculator_golden_case_id');
            $table->json('observed_result_snapshot');
            $table->text('calculation_explanation');
            $table->boolean('passed');
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['firm_id', 'calculator_golden_case_id'])->references(['firm_id', 'id'])->on('calculator_golden_cases')->restrictOnDelete();
            $table->index(['firm_id', 'calculator_golden_case_id', 'verified_at'], 'calculator_case_verification_lookup');
        });

        Schema::table('obligation_rule_versions', function (Blueprint $table) {
            $table->ulid('calculator_golden_case_set_id')->nullable()->after('calculator_key');
            $table->foreign(['firm_id', 'calculator_golden_case_set_id'])->references(['firm_id', 'id'])->on('calculator_golden_case_sets')->restrictOnDelete();
        });

        $this->restoreRuleVersionGuards();
        $this->guardEvidence();
    }

    public function down(): void
    {
        foreach (['calculator_golden_case_verifications', 'calculator_golden_cases'] as $table) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }
        Schema::table('obligation_rule_versions', function (Blueprint $table) {
            $table->dropForeign(['firm_id', 'calculator_golden_case_set_id']);
            $table->dropColumn('calculator_golden_case_set_id');
        });
        $this->restoreRuleVersionGuards();
        Schema::dropIfExists('calculator_golden_case_verifications');
        Schema::dropIfExists('calculator_golden_cases');
        Schema::dropIfExists('calculator_golden_case_sets');
    }

    private function guardEvidence(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER calculator_golden_case_sets_no_delete BEFORE DELETE ON calculator_golden_case_sets BEGIN SELECT RAISE(ABORT, 'Golden-case sets cannot be deleted.'); END;");
            DB::unprepared(
                "CREATE TRIGGER calculator_golden_case_sets_invalid_update BEFORE UPDATE ON calculator_golden_case_sets
                 WHEN NOT (
                    OLD.status = 'draft' AND NEW.status = 'approved' AND
                    OLD.calculator_key IS NEW.calculator_key AND OLD.version IS NEW.version AND
                    OLD.name IS NEW.name AND OLD.prepared_by IS NEW.prepared_by AND
                    NEW.approved_by IS NOT NULL AND NEW.approved_at IS NOT NULL
                 )
                 BEGIN SELECT RAISE(ABORT, 'Invalid golden-case set update.'); END;",
            );
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER calculator_golden_case_sets_no_delete BEFORE DELETE ON calculator_golden_case_sets FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Golden-case sets cannot be deleted.'; END");
            DB::unprepared(
                "CREATE TRIGGER calculator_golden_case_sets_invalid_update BEFORE UPDATE ON calculator_golden_case_sets FOR EACH ROW
                 BEGIN
                    IF NOT (
                        OLD.status = 'draft' AND NEW.status = 'approved' AND
                        OLD.calculator_key <=> NEW.calculator_key AND OLD.version <=> NEW.version AND
                        OLD.name <=> NEW.name AND OLD.prepared_by <=> NEW.prepared_by AND
                        NEW.approved_by IS NOT NULL AND NEW.approved_at IS NOT NULL
                    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid golden-case set update.'; END IF;
                 END",
            );
        }
        foreach (['calculator_golden_cases', 'calculator_golden_case_verifications'] as $table) {
            foreach (['update', 'delete'] as $event) {
                $name = "{$table}_no_{$event}";
                $message = 'Calculator golden-case evidence is append-only.';
                if ($driver === 'sqlite') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
                } elseif ($driver === 'mysql') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END");
                }
            }
        }
    }

    private function restoreRuleVersionGuards(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (['obligation_rule_versions_no_delete', 'obligation_rule_versions_no_update', 'obligation_rule_versions_invalid_status'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger};");
        }
        $contentMessage = 'Rule version content is immutable after draft.';
        $transitionMessage = 'Invalid rule version lifecycle transition.';
        $goldenGuardSqlite = Schema::hasColumn('obligation_rule_versions', 'calculator_golden_case_set_id')
            ? ' OR OLD.calculator_golden_case_set_id IS NOT NEW.calculator_golden_case_set_id'
            : '';
        $goldenGuardMysql = Schema::hasColumn('obligation_rule_versions', 'calculator_golden_case_set_id')
            ? ' OR NOT (OLD.calculator_golden_case_set_id <=> NEW.calculator_golden_case_set_id)'
            : '';
        $valid = "
            (OLD.status = NEW.status) OR
            (OLD.status = 'draft' AND NEW.status = 'under_review') OR
            (OLD.status = 'under_review' AND NEW.status = 'approved'
                AND NEW.source_last_verified_on IS NOT NULL
                AND NEW.verified_by IS NOT NULL
                AND NEW.verified_at IS NOT NULL
                AND NEW.approved_at IS NOT NULL) OR
            (OLD.status = 'approved' AND NEW.status = 'published' AND NEW.published_at IS NOT NULL) OR
            (OLD.status = 'published' AND NEW.status IN ('superseded', 'retired'))
        ";

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER obligation_rule_versions_no_delete BEFORE DELETE ON obligation_rule_versions BEGIN SELECT RAISE(ABORT, 'Rule versions cannot be deleted.'); END;");
            DB::unprepared(
                "CREATE TRIGGER obligation_rule_versions_no_update
                 BEFORE UPDATE ON obligation_rule_versions
                 WHEN (
                    OLD.status <> 'draft' AND (
                        OLD.effective_from IS NOT NEW.effective_from OR OLD.effective_to IS NOT NEW.effective_to OR
                        OLD.applicability_criteria IS NOT NEW.applicability_criteria OR OLD.calculator_key IS NOT NEW.calculator_key OR
                        OLD.parameters IS NOT NEW.parameters OR OLD.official_source_title IS NOT NEW.official_source_title OR
                        OLD.official_source_url IS NOT NEW.official_source_url OR OLD.source_published_on IS NOT NEW.source_published_on OR
                        OLD.change_summary IS NOT NEW.change_summary
                    )
                 ) OR (
                    OLD.status = NEW.status AND OLD.status <> 'draft' AND (
                        OLD.source_last_verified_on IS NOT NEW.source_last_verified_on OR OLD.verified_by IS NOT NEW.verified_by OR
                        OLD.verified_at IS NOT NEW.verified_at OR OLD.approved_at IS NOT NEW.approved_at OR
                        OLD.published_at IS NOT NEW.published_at{$goldenGuardSqlite}
                    )
                 )
                 BEGIN SELECT RAISE(ABORT, '{$contentMessage}'); END;",
            );
            DB::unprepared("CREATE TRIGGER obligation_rule_versions_invalid_status BEFORE UPDATE ON obligation_rule_versions WHEN NOT ({$valid}) BEGIN SELECT RAISE(ABORT, '{$transitionMessage}'); END;");
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER obligation_rule_versions_no_delete BEFORE DELETE ON obligation_rule_versions FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rule versions cannot be deleted.'; END");
            DB::unprepared(
                "CREATE TRIGGER obligation_rule_versions_no_update
                 BEFORE UPDATE ON obligation_rule_versions FOR EACH ROW
                 BEGIN
                    IF (
                        OLD.status <> 'draft' AND (
                            NOT (OLD.effective_from <=> NEW.effective_from) OR NOT (OLD.effective_to <=> NEW.effective_to) OR
                            NOT (OLD.applicability_criteria <=> NEW.applicability_criteria) OR NOT (OLD.calculator_key <=> NEW.calculator_key) OR
                            NOT (OLD.parameters <=> NEW.parameters) OR NOT (OLD.official_source_title <=> NEW.official_source_title) OR
                            NOT (OLD.official_source_url <=> NEW.official_source_url) OR NOT (OLD.source_published_on <=> NEW.source_published_on) OR
                            NOT (OLD.change_summary <=> NEW.change_summary)
                        )
                    ) OR (
                        OLD.status = NEW.status AND OLD.status <> 'draft' AND (
                            NOT (OLD.source_last_verified_on <=> NEW.source_last_verified_on) OR NOT (OLD.verified_by <=> NEW.verified_by) OR
                            NOT (OLD.verified_at <=> NEW.verified_at) OR NOT (OLD.approved_at <=> NEW.approved_at) OR
                            NOT (OLD.published_at <=> NEW.published_at){$goldenGuardMysql}
                        )
                    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$contentMessage}'; END IF;
                 END",
            );
            DB::unprepared(
                "CREATE TRIGGER obligation_rule_versions_invalid_status BEFORE UPDATE ON obligation_rule_versions FOR EACH ROW
                 BEGIN IF NOT ({$valid}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$transitionMessage}'; END IF; END",
            );
        }
    }
};
