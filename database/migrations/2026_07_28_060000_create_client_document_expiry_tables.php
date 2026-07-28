<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const GUARDED_TABLES = [
        'document_type_versions' => 'Published document type versions are immutable.',
        'client_documents' => 'Client document metadata is append-only.',
        'document_expiry_reminders' => 'Document expiry reminders are append-only.',
    ];

    public function up(): void
    {
        Schema::create('document_type_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->string('key', 64);
            $table->unsignedInteger('version');
            $table->string('name', 100);
            $table->boolean('expiry_required');
            $table->json('reminder_days');
            $table->unsignedSmallInteger('overdue_repeat_days')->nullable();
            $table->timestamp('published_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['firm_id', 'id']);
            $table->unique(['firm_id', 'key', 'version']);
            $table->index(['firm_id', 'key', 'published_at']);
        });

        Schema::create('client_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_id');
            $table->ulid('document_type_version_id');
            $table->ulid('supersedes_client_document_id')->nullable();
            $table->string('reference_label', 100)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['firm_id', 'id']);
            $table->foreign(['firm_id', 'client_id'])
                ->references(['firm_id', 'id'])->on('clients')->restrictOnDelete();
            $table->foreign(['firm_id', 'document_type_version_id'])
                ->references(['firm_id', 'id'])->on('document_type_versions')->restrictOnDelete();
            $table->foreign(['firm_id', 'supersedes_client_document_id'])
                ->references(['firm_id', 'id'])->on('client_documents')->restrictOnDelete();
            $table->unique(['firm_id', 'supersedes_client_document_id']);
            $table->index(['firm_id', 'expires_on']);
            $table->index(['firm_id', 'client_id', 'document_type_version_id']);
        });

        Schema::create('document_expiry_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('client_document_id');
            $table->string('kind', 32);
            $table->date('scheduled_for');
            $table->integer('days_from_expiry');
            $table->timestamp('generated_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['firm_id', 'client_document_id'])
                ->references(['firm_id', 'id'])->on('client_documents')->restrictOnDelete();
            $table->unique(['firm_id', 'client_document_id', 'kind', 'scheduled_for']);
            $table->index(['firm_id', 'scheduled_for', 'kind']);
        });

        $this->guardImmutableTables();
    }

    public function down(): void
    {
        foreach (array_keys(self::GUARDED_TABLES) as $table) {
            foreach (['update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_{$event};");
            }
        }

        Schema::dropIfExists('document_expiry_reminders');
        Schema::dropIfExists('client_documents');
        Schema::dropIfExists('document_type_versions');
    }

    private function guardImmutableTables(): void
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
