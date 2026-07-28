<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Actions\Audit\RecordAudit;
use App\Models\NotificationReadReceipt;
use App\Models\NotificationRequest;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class MarkNotificationRead
{
    public function __construct(private FirmContext $context, private RecordAudit $audit) {}

    public function handle(User $actor, NotificationRequest $request): NotificationReadReceipt
    {
        if ($request->firm_id !== $this->context->firmId() || $request->recipient_user_id !== $actor->id) {
            throw new AuthorizationException('Only the active-firm recipient may mark this notice read.');
        }

        return DB::transaction(function () use ($actor, $request): NotificationReadReceipt {
            $receipt = NotificationReadReceipt::query()->firstOrCreate(
                ['notification_id' => $request->id],
                ['read_by' => $actor->id, 'read_at' => now()],
            );
            if ($receipt->wasRecentlyCreated) {
                $this->audit->handle('firm.notification.read', $actor, $receipt, [], ['notification_request_id' => $request->id]);
            }

            return $receipt->refresh();
        }, 3);
    }
}
