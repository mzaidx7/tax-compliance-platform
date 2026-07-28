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
        Schema::create('notification_read_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('firm_id');
            $table->ulid('notification_id');
            $table->foreignId('read_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('read_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['firm_id', 'notification_id']);
            $table->foreign(['firm_id', 'notification_id'])->references(['firm_id', 'id'])->on('notifications')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER notification_read_receipts_prevent_update BEFORE UPDATE ON notification_read_receipts BEGIN SELECT RAISE(ABORT, 'Notification read receipts are append-only'); END;");
            DB::unprepared("CREATE TRIGGER notification_read_receipts_prevent_delete BEFORE DELETE ON notification_read_receipts BEGIN SELECT RAISE(ABORT, 'Notification read receipts are append-only'); END;");
        }
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER notification_read_receipts_prevent_update BEFORE UPDATE ON notification_read_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Notification read receipts are append-only'");
            DB::unprepared("CREATE TRIGGER notification_read_receipts_prevent_delete BEFORE DELETE ON notification_read_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Notification read receipts are append-only'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_read_receipts');
    }
};
