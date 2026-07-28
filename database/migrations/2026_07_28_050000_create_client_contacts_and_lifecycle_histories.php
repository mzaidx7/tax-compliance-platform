<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const GUARDED_TABLES = [
        'client_status_changes' => 'Client status history is append-only.',
        'client_service_enrollment_status_changes' => 'Client service enrollment status history is append-only.',
    ];

    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->string('name');
            $table->string('position', 100)->nullable();
            $table->string('purpose', 32);
            $table->string('preferred_channel', 16);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['firm_id', 'id']);
            $table->foreign(['firm_id', 'client_id'])
                ->references(['firm_id', 'id'])->on('clients')->restrictOnDelete();
            $table->index(['firm_id', 'client_id', 'purpose', 'is_active']);
        });

        Schema::create('client_status_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->string('previous_status', 32);
            $table->string('new_status', 32);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('changed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'client_id'])
                ->references(['firm_id', 'id'])->on('clients')->restrictOnDelete();
            $table->index(['firm_id', 'client_id', 'changed_at']);
        });

        Schema::create('client_service_enrollment_status_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_service_enrollment_id');
            $table->string('previous_status', 24);
            $table->string('new_status', 24);
            $table->date('effective_on');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('changed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'client_service_enrollment_id'])
                ->references(['firm_id', 'id'])->on('client_service_enrollments')->restrictOnDelete();
            $table->index(['firm_id', 'client_service_enrollment_id', 'changed_at']);
        });

        $this->guardHistory();
    }

    public function down(): void
    {
        foreach (array_keys(self::GUARDED_TABLES) as $table) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }

        Schema::dropIfExists('client_service_enrollment_status_changes');
        Schema::dropIfExists('client_status_changes');
        Schema::dropIfExists('client_contacts');
    }

    private function guardHistory(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (self::GUARDED_TABLES as $table => $message) {
            foreach (['update', 'delete'] as $event) {
                $triggerName = "{$table}_no_{$event}";

                if ($driver === 'sqlite') {
                    DB::unprepared(
                        "CREATE TRIGGER {$triggerName}
                         BEFORE {$event} ON {$table}
                         BEGIN
                             SELECT RAISE(ABORT, '{$message}');
                         END;",
                    );
                } elseif ($driver === 'mysql') {
                    $upperEvent = strtoupper($event);
                    DB::unprepared(
                        "CREATE TRIGGER {$triggerName}
                         BEFORE {$upperEvent} ON {$table}
                         FOR EACH ROW
                         BEGIN
                             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                         END",
                    );
                }
            }
        }
    }
};
