<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_rule_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->string('key', 64);
            $table->string('name', 120);
            $table->string('obligation_type', 100);
            $table->string('jurisdiction', 100);
            $table->string('authority', 120);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'key']);
        });

        Schema::create('obligation_rule_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('obligation_rule_template_id');
            $table->unsignedInteger('version');
            $table->string('status', 24);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('applicability_criteria');
            $table->string('calculator_key', 100);
            $table->json('parameters');
            $table->string('official_source_title', 255);
            $table->text('official_source_url');
            $table->date('source_published_on')->nullable();
            $table->date('source_last_verified_on')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('change_summary', 500);
            $table->timestamps();

            $table->unique(['firm_id', 'id']);
            $table->foreign(['firm_id', 'obligation_rule_template_id'])
                ->references(['firm_id', 'id'])->on('obligation_rule_templates')->restrictOnDelete();
            $table->unique(['firm_id', 'obligation_rule_template_id', 'version']);
            $table->index(['firm_id', 'status', 'effective_from']);
        });

        Schema::create('obligation_rule_version_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('obligation_rule_version_id');
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('acted_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'obligation_rule_version_id'])
                ->references(['firm_id', 'id'])->on('obligation_rule_versions')->restrictOnDelete();
            $table->index(['firm_id', 'obligation_rule_version_id', 'occurred_at']);
        });

        $this->guardTables();
    }

    public function down(): void
    {
        foreach (['obligation_rule_templates', 'obligation_rule_versions', 'obligation_rule_version_events'] as $table) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }
        DB::unprepared('DROP TRIGGER IF EXISTS obligation_rule_versions_invalid_status;');

        Schema::dropIfExists('obligation_rule_version_events');
        Schema::dropIfExists('obligation_rule_versions');
        Schema::dropIfExists('obligation_rule_templates');
    }

    private function guardTables(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $this->guardAlways($driver, 'obligation_rule_templates', 'Rule templates are immutable.');
        $this->guardAlways($driver, 'obligation_rule_version_events', 'Rule lifecycle history is append-only.');
        $this->guardVersionDelete($driver);
        $this->guardVersionContent($driver);
        $this->guardVersionTransitions($driver);
    }

    private function guardAlways(string $driver, string $table, string $message): void
    {
        foreach (['update', 'delete'] as $event) {
            $trigger = "{$table}_no_{$event}";
            if ($driver === 'sqlite') {
                DB::connection()->getPdo()->exec(
                    "CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT, '{$message}'); END;",
                );
            } elseif ($driver === 'mysql') {
                $upper = strtoupper($event);
                DB::connection()->getPdo()->exec(
                    "CREATE TRIGGER {$trigger} BEFORE {$upper} ON {$table} FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END",
                );
            }
        }
    }

    private function guardVersionDelete(string $driver): void
    {
        $message = 'Rule versions cannot be deleted.';
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER obligation_rule_versions_no_delete BEFORE DELETE ON obligation_rule_versions BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER obligation_rule_versions_no_delete BEFORE DELETE ON obligation_rule_versions FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END");
        }
    }

    private function guardVersionContent(string $driver): void
    {
        $message = 'Rule version content is immutable after draft.';
        if ($driver === 'sqlite') {
            DB::unprepared(
                "CREATE TRIGGER obligation_rule_versions_no_update
                 BEFORE UPDATE ON obligation_rule_versions
                 WHEN (
                    OLD.status <> 'draft' AND (
                        OLD.effective_from IS NOT NEW.effective_from OR
                        OLD.effective_to IS NOT NEW.effective_to OR
                        OLD.applicability_criteria IS NOT NEW.applicability_criteria OR
                        OLD.calculator_key IS NOT NEW.calculator_key OR
                        OLD.parameters IS NOT NEW.parameters OR
                        OLD.official_source_title IS NOT NEW.official_source_title OR
                        OLD.official_source_url IS NOT NEW.official_source_url OR
                        OLD.source_published_on IS NOT NEW.source_published_on OR
                        OLD.change_summary IS NOT NEW.change_summary
                    )
                 ) OR (
                    OLD.status = NEW.status AND OLD.status <> 'draft' AND (
                        OLD.source_last_verified_on IS NOT NEW.source_last_verified_on OR
                        OLD.verified_by IS NOT NEW.verified_by OR
                        OLD.verified_at IS NOT NEW.verified_at OR
                        OLD.approved_at IS NOT NEW.approved_at OR
                        OLD.published_at IS NOT NEW.published_at
                    )
                 )
                 BEGIN SELECT RAISE(ABORT, '{$message}'); END;",
            );
        } elseif ($driver === 'mysql') {
            DB::unprepared(
                "CREATE TRIGGER obligation_rule_versions_no_update
                 BEFORE UPDATE ON obligation_rule_versions
                 FOR EACH ROW
                 BEGIN
                    IF (
                        OLD.status <> 'draft' AND (
                            NOT (OLD.effective_from <=> NEW.effective_from) OR
                            NOT (OLD.effective_to <=> NEW.effective_to) OR
                            NOT (OLD.applicability_criteria <=> NEW.applicability_criteria) OR
                            NOT (OLD.calculator_key <=> NEW.calculator_key) OR
                            NOT (OLD.parameters <=> NEW.parameters) OR
                            NOT (OLD.official_source_title <=> NEW.official_source_title) OR
                            NOT (OLD.official_source_url <=> NEW.official_source_url) OR
                            NOT (OLD.source_published_on <=> NEW.source_published_on) OR
                            NOT (OLD.change_summary <=> NEW.change_summary)
                        )
                    ) OR (
                        OLD.status = NEW.status AND OLD.status <> 'draft' AND (
                            NOT (OLD.source_last_verified_on <=> NEW.source_last_verified_on) OR
                            NOT (OLD.verified_by <=> NEW.verified_by) OR
                            NOT (OLD.verified_at <=> NEW.verified_at) OR
                            NOT (OLD.approved_at <=> NEW.approved_at) OR
                            NOT (OLD.published_at <=> NEW.published_at)
                        )
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                    END IF;
                 END",
            );
        }
    }

    private function guardVersionTransitions(string $driver): void
    {
        $message = 'Invalid rule version lifecycle transition.';
        $valid = "
            (OLD.status = NEW.status) OR
            (OLD.status = 'draft' AND NEW.status = 'under_review') OR
            (OLD.status = 'under_review' AND NEW.status = 'approved'
                AND NEW.source_last_verified_on IS NOT NULL
                AND NEW.verified_by IS NOT NULL
                AND NEW.verified_at IS NOT NULL
                AND NEW.approved_at IS NOT NULL) OR
            (OLD.status = 'approved' AND NEW.status = 'published'
                AND NEW.published_at IS NOT NULL) OR
            (OLD.status = 'published' AND NEW.status IN ('superseded', 'retired'))
        ";

        if ($driver === 'sqlite') {
            DB::unprepared(
                "CREATE TRIGGER obligation_rule_versions_invalid_status
                 BEFORE UPDATE ON obligation_rule_versions
                 WHEN NOT ({$valid})
                 BEGIN SELECT RAISE(ABORT, '{$message}'); END;",
            );
        } elseif ($driver === 'mysql') {
            DB::unprepared(
                "CREATE TRIGGER obligation_rule_versions_invalid_status
                 BEFORE UPDATE ON obligation_rule_versions
                 FOR EACH ROW
                 BEGIN
                    IF NOT ({$valid}) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                    END IF;
                 END",
            );
        }
    }
};
