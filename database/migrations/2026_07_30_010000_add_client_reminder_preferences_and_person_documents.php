<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('document_reminder_mode', 16)->default('review')->after('primary_phone');
            $table->string('vat_reminder_mode', 16)->default('review')->after('document_reminder_mode');
            $table->string('corporate_tax_reminder_mode', 16)->default('review')->after('vat_reminder_mode');
            $table->timestamp('automatic_reminders_confirmed_at')->nullable()->after('corporate_tax_reminder_mode');
            $table->foreignId('automatic_reminders_confirmed_by')->nullable()
                ->after('automatic_reminders_confirmed_at')
                ->constrained('users')
                ->restrictOnDelete();
        });

        Schema::table('client_people', function (Blueprint $table): void {
            $table->unique(['firm_id', 'client_id', 'id']);
        });

        Schema::table('client_documents', function (Blueprint $table): void {
            $table->ulid('client_person_id')->nullable()->after('client_id');
            $table->foreign(['firm_id', 'client_id', 'client_person_id'])
                ->references(['firm_id', 'client_id', 'id'])
                ->on('client_people')
                ->restrictOnDelete();
            $table->index(['firm_id', 'client_person_id', 'expires_on']);
        });

        $this->guardClientDocuments();
    }

    public function down(): void
    {
        Schema::table('client_documents', function (Blueprint $table): void {
            $table->dropForeign(['firm_id', 'client_id', 'client_person_id']);
            $table->dropIndex(['firm_id', 'client_person_id', 'expires_on']);
            $table->dropColumn('client_person_id');
        });

        Schema::table('client_people', function (Blueprint $table): void {
            $table->dropUnique(['firm_id', 'client_id', 'id']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('automatic_reminders_confirmed_by');
            $table->dropColumn([
                'document_reminder_mode',
                'vat_reminder_mode',
                'corporate_tax_reminder_mode',
                'automatic_reminders_confirmed_at',
            ]);
        });

        $this->guardClientDocuments();
    }

    private function guardClientDocuments(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (['update', 'delete'] as $event) {
            $trigger = "client_documents_no_{$event}";
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger};");

            if ($driver === 'sqlite') {
                DB::unprepared(
                    "CREATE TRIGGER {$trigger}
                     BEFORE {$event} ON client_documents
                     BEGIN
                         SELECT RAISE(ABORT, 'Client document metadata is append-only.');
                     END;",
                );
            } elseif ($driver === 'mysql') {
                DB::unprepared(
                    "CREATE TRIGGER {$trigger}
                     BEFORE ".strtoupper($event)." ON client_documents
                     FOR EACH ROW
                     BEGIN
                         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Client document metadata is append-only.';
                     END",
                );
            }
        }
    }
};
