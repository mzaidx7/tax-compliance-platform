<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_candidates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('firm_id')->constrained()->cascadeOnDelete();
            $table->char('candidate_key', 64);
            $table->foreignUlid('first_party_record_id');
            $table->foreignUlid('second_party_record_id');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'candidate_key']);
            $table->foreign(['firm_id', 'first_party_record_id'])->references(['firm_id', 'id'])->on('party_records')->restrictOnDelete();
            $table->foreign(['firm_id', 'second_party_record_id'])->references(['firm_id', 'id'])->on('party_records')->restrictOnDelete();
        });

        Schema::create('duplicate_candidate_signals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('duplicate_candidate_id');
            $table->string('signal_type', 48);
            $table->text('first_normalized_value');
            $table->text('second_normalized_value');
            $table->string('normalizer_version', 80);
            $table->string('contribution_explanation', 500);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'duplicate_candidate_id', 'signal_type'], 'duplicate_signal_type_unique');
            $table->foreign(['firm_id', 'duplicate_candidate_id'])->references(['firm_id', 'id'])->on('duplicate_candidates')->restrictOnDelete();
        });

        Schema::create('duplicate_candidate_decisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('duplicate_candidate_id');
            $table->string('outcome', 24);
            $table->string('reason', 500);
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'duplicate_candidate_id']);
            $table->foreign(['firm_id', 'duplicate_candidate_id'])->references(['firm_id', 'id'])->on('duplicate_candidates')->restrictOnDelete();
        });

        foreach (['duplicate_candidates', 'duplicate_candidate_signals', 'duplicate_candidate_decisions'] as $table) {
            $this->guard($table);
        }
    }

    public function down(): void
    {
        foreach (['duplicate_candidate_decisions', 'duplicate_candidate_signals', 'duplicate_candidates'] as $table) {
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
