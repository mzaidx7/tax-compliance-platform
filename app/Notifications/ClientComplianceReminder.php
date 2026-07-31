<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ClientReminderCategory;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ClientComplianceReminder extends Notification
{
    public function __construct(
        private readonly string $firmName,
        private readonly string $clientName,
        private readonly ClientReminderCategory $category,
        private readonly string $itemName,
        private readonly ?string $personName,
        private readonly string $eventDate,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Action reminder for {$this->clientName}")
            ->greeting("Hello {$this->clientName}")
            ->line("{$this->firmName} is reminding you about an upcoming {$this->category->label()} requirement.")
            ->line("Item: {$this->itemName}.");

        if ($this->personName !== null) {
            $message->line("Person: {$this->personName}.");
        }

        return $message
            ->line("Relevant date: {$this->eventDate}.")
            ->line('Please contact your usual adviser if the information has already been renewed, filed or updated.')
            ->line('This email does not contain passport, Emirates ID, trade licence or tax registration numbers.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
