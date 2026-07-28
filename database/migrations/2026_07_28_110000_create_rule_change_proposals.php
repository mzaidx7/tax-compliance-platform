<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const GUARDED_TABLES = ['rule_change_proposals', 'rule_change_proposal_decisions'];

    public function up(): void
    {
        Schema::create('rule_change_proposals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('original_obligation_id');
            $table->ulid('proposed_rule_version_id');
            $table->ulid('preview_run_id');
            $table->date('original_statutory_due_date');
            $table->date('proposed_statutory_due_date');
            $table->string('reason', 500);
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('proposed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'original_obligation_id'])->references(['firm_id', 'id'])->on('obligations')->restrictOnDelete();
            $table->foreign(['firm_id', 'proposed_rule_version_id'])->references(['firm_id', 'id'])->on('obligation_rule_versions')->restrictOnDelete();
            $table->foreign(['firm_id', 'preview_run_id'])->references(['firm_id', 'id'])->on('obligation_generation_runs')->restrictOnDelete();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'original_obligation_id', 'preview_run_id'], 'rule_change_proposal_identity');
            $table->index(['firm_id', 'original_obligation_id', 'proposed_at'], 'rule_change_proposal_lookup');
        });

        Schema::create('rule_change_proposal_decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('rule_change_proposal_id');
            $table->string('decision', 16);
            $table->ulid('replacement_obligation_id');
            $table->string('reason', 500);
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'rule_change_proposal_id'])->references(['firm_id', 'id'])->on('rule_change_proposals')->restrictOnDelete();
            $table->foreign(['firm_id', 'replacement_obligation_id'])->references(['firm_id', 'id'])->on('obligations')->restrictOnDelete();
            $table->unique(['firm_id', 'rule_change_proposal_id']);
        });

        $this->guardHistory();
    }

    public function down(): void
    {
        foreach (array_reverse(self::GUARDED_TABLES) as $table) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }
        Schema::dropIfExists('rule_change_proposal_decisions');
        Schema::dropIfExists('rule_change_proposals');
    }

    private function guardHistory(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (self::GUARDED_TABLES as $table) {
            foreach (['update', 'delete'] as $event) {
                $name = "{$table}_no_{$event}";
                $message = 'Rule change proposal evidence is append-only.';
                if ($driver === 'sqlite') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
                } elseif ($driver === 'mysql') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($event)." ON {$table} FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END");
                }
            }
        }
    }
};
