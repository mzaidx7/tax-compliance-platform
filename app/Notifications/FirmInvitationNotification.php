<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FirmInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $plainTextToken,
        public string $firmName,
        public string $expiresAt,
        public string $inviterName,
    ) {
        $this->onQueue((string) config('platform.queue.name', 'platform'));
        $this->afterCommit();
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
        return (new MailMessage)
            ->subject("Invitation to {$this->firmName}")
            ->greeting('You have been invited')
            ->line("{$this->inviterName} invited you to join {$this->firmName} on the TBT Compliance Platform.")
            ->line("This invitation expires {$this->expiresAt}.")
            ->action('Review invitation', route('invitations.show', $this->plainTextToken))
            ->line('Only accept this invitation if you recognise the firm and inviter.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
