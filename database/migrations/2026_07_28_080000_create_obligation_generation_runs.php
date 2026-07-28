<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_periods', function (Blueprint $table) {
            $table->unique(['firm_id', 'id']);
        });

        Schema::create('obligation_generation_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->string('status', 16);
            $table->char('deterministic_key', 64);
            $table->ulid('preview_run_id')->nullable();
            $table->ulid('client_id');
            $table->ulid('client_service_enrollment_id');
            $table->ulid('tax_period_id')->nullable();
            $table->ulid('obligation_rule_version_id');
            $table->json('input_snapshot');
            $table->json('parameter_snapshot');
            $table->json('result_snapshot');
            $table->date('statutory_due_date');
            $table->date('internal_target_date')->nullable();
            $table->text('calculation_explanation');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'status', 'deterministic_key']);
            $table->foreign(['firm_id', 'client_id'])
                ->references(['firm_id', 'id'])->on('clients')->restrictOnDelete();
            $table->foreign(['firm_id', 'client_service_enrollment_id'])
                ->references(['firm_id', 'id'])->on('client_service_enrollments')->restrictOnDelete();
            $table->foreign(['firm_id', 'tax_period_id'])
                ->references(['firm_id', 'id'])->on('tax_periods')->restrictOnDelete();
            $table->foreign(['firm_id', 'obligation_rule_version_id'])
                ->references(['firm_id', 'id'])->on('obligation_rule_versions')->restrictOnDelete();
            $table->foreign(['firm_id', 'preview_run_id'])
                ->references(['firm_id', 'id'])->on('obligation_generation_runs')->restrictOnDelete();
            $table->index(['firm_id', 'client_id', 'created_at']);
        });

        Schema::table('obligations', function (Blueprint $table) {
            $table->ulid('client_service_enrollment_id')->nullable()->after('client_id');
            $table->ulid('tax_period_id')->nullable()->after('client_service_enrollment_id');
            $table->ulid('obligation_rule_version_id')->nullable()->after('tax_period_id');
            $table->ulid('generation_run_id')->nullable()->after('obligation_rule_version_id');
            $table->char('generation_key', 64)->nullable()->after('generation_run_id');
            $table->json('calculation_input_snapshot')->nullable()->after('generation_key');
            $table->json('calculation_parameter_snapshot')->nullable()->after('calculation_input_snapshot');
            $table->json('calculation_result_snapshot')->nullable()->after('calculation_parameter_snapshot');
            $table->text('calculation_explanation')->nullable()->after('calculation_result_snapshot');

            $table->foreign(['firm_id', 'client_service_enrollment_id'])
                ->references(['firm_id', 'id'])->on('client_service_enrollments')->restrictOnDelete();
            $table->foreign(['firm_id', 'tax_period_id'])
                ->references(['firm_id', 'id'])->on('tax_periods')->restrictOnDelete();
            $table->foreign(['firm_id', 'obligation_rule_version_id'])
                ->references(['firm_id', 'id'])->on('obligation_rule_versions')->restrictOnDelete();
            $table->foreign(['firm_id', 'generation_run_id'])
                ->references(['firm_id', 'id'])->on('obligation_generation_runs')->restrictOnDelete();
            $table->unique(['firm_id', 'generation_key']);
        });

        $this->guardImmutableRuns();
        $this->guardGeneratedObligationSnapshots();
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS obligation_generation_runs_no_update;');
        DB::unprepared('DROP TRIGGER IF EXISTS obligation_generation_runs_no_delete;');
        DB::unprepared('DROP TRIGGER IF EXISTS obligations_generated_snapshot_no_update;');

        Schema::table('obligations', function (Blueprint $table) {
            $table->dropUnique(['firm_id', 'generation_key']);
            $table->dropForeign(['firm_id', 'generation_run_id']);
            $table->dropForeign(['firm_id', 'obligation_rule_version_id']);
            $table->dropForeign(['firm_id', 'tax_period_id']);
            $table->dropForeign(['firm_id', 'client_service_enrollment_id']);
            $table->dropColumn([
                'client_service_enrollment_id',
                'tax_period_id',
                'obligation_rule_version_id',
                'generation_run_id',
                'generation_key',
                'calculation_input_snapshot',
                'calculation_parameter_snapshot',
                'calculation_result_snapshot',
                'calculation_explanation',
            ]);
        });

        Schema::dropIfExists('obligation_generation_runs');

        Schema::table('tax_periods', function (Blueprint $table) {
            $table->dropUnique(['firm_id', 'id']);
        });
    }

    private function guardImmutableRuns(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (['update', 'delete'] as $event) {
            $trigger = "obligation_generation_runs_no_{$event}";
            $message = 'Obligation generation runs are immutable.';
            if ($driver === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$event} ON obligation_generation_runs BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
            } elseif ($driver === 'mysql') {
                $upper = strtoupper($event);
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$upper} ON obligation_generation_runs FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END");
            }
        }
    }

    private function guardGeneratedObligationSnapshots(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $message = 'Generated obligation snapshots are immutable.';

        if ($driver === 'sqlite') {
            DB::unprepared(
                "CREATE TRIGGER obligations_generated_snapshot_no_update
                 BEFORE UPDATE ON obligations
                 WHEN OLD.origin = 'governed_rule' AND (
                    OLD.client_id IS NOT NEW.client_id OR
                    OLD.client_service_enrollment_id IS NOT NEW.client_service_enrollment_id OR
                    OLD.tax_period_id IS NOT NEW.tax_period_id OR
                    OLD.obligation_rule_version_id IS NOT NEW.obligation_rule_version_id OR
                    OLD.generation_run_id IS NOT NEW.generation_run_id OR
                    OLD.generation_key IS NOT NEW.generation_key OR
                    OLD.calculation_input_snapshot IS NOT NEW.calculation_input_snapshot OR
                    OLD.calculation_parameter_snapshot IS NOT NEW.calculation_parameter_snapshot OR
                    OLD.calculation_result_snapshot IS NOT NEW.calculation_result_snapshot OR
                    OLD.calculation_explanation IS NOT NEW.calculation_explanation OR
                    OLD.statutory_due_date IS NOT NEW.statutory_due_date OR
                    OLD.internal_target_date IS NOT NEW.internal_target_date
                 )
                 BEGIN SELECT RAISE(ABORT, '{$message}'); END;",
            );
        } elseif ($driver === 'mysql') {
            DB::unprepared(
                "CREATE TRIGGER obligations_generated_snapshot_no_update
                 BEFORE UPDATE ON obligations
                 FOR EACH ROW
                 BEGIN
                    IF OLD.origin = 'governed_rule' AND (
                        NOT (OLD.client_id <=> NEW.client_id) OR
                        NOT (OLD.client_service_enrollment_id <=> NEW.client_service_enrollment_id) OR
                        NOT (OLD.tax_period_id <=> NEW.tax_period_id) OR
                        NOT (OLD.obligation_rule_version_id <=> NEW.obligation_rule_version_id) OR
                        NOT (OLD.generation_run_id <=> NEW.generation_run_id) OR
                        NOT (OLD.generation_key <=> NEW.generation_key) OR
                        NOT (OLD.calculation_input_snapshot <=> NEW.calculation_input_snapshot) OR
                        NOT (OLD.calculation_parameter_snapshot <=> NEW.calculation_parameter_snapshot) OR
                        NOT (OLD.calculation_result_snapshot <=> NEW.calculation_result_snapshot) OR
                        NOT (OLD.calculation_explanation <=> NEW.calculation_explanation) OR
                        NOT (OLD.statutory_due_date <=> NEW.statutory_due_date) OR
                        NOT (OLD.internal_target_date <=> NEW.internal_target_date)
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                    END IF;
                 END",
            );
        }
    }
};
