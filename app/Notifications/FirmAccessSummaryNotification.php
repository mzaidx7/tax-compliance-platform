<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\FirmMembershipStatus;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\Messages\MailMessage;

final class FirmAccessSummaryNotification extends FirmNotification
{
    public function triggeringRecord(): FirmMembership
    {
        return FirmMembership::query()
            ->where('user_id', $this->recipientUserId())
            ->where('status', FirmMembershipStatus::Active)
            ->sole();
    }

    public function templateKey(): string
    {
        return 'firm_access_summary';
    }

    public function templateVersion(): int
    {
        return 1;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User || $notifiable->getKey() !== $this->recipientUserId()) {
            throw new AuthorizationException('The access summary recipient is invalid.');
        }

        $firm = app(FirmContext::class)->firm();
        $membership = FirmMembership::query()
            ->where('user_id', $notifiable->getKey())
            ->where('status', FirmMembershipStatus::Active)
            ->sole();

        return (new MailMessage)
            ->subject("Your access to {$firm->name}")
            ->greeting("Hello {$notifiable->name}")
            ->line("Your current role for {$firm->name} is {$membership->role->label()}.")
            ->action('Open compliance platform', route('dashboard'))
            ->line('If you did not expect this access, contact your firm administrator.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
