<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PaymentRecord;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent when a payment is explicitly recorded as overdue.
 *
 * This notification never evaluates whether a payment is late. It reports a
 * state a member already recorded through `TransitionPaymentRecord`, and it
 * never carries a payment reference or any settlement credential.
 */
final class PaymentOverdueNotification extends FirmNotification
{
    public function __construct(
        string $firmId,
        int $recipientUserId,
        private readonly string $paymentRecordId,
    ) {
        parent::__construct($firmId, $recipientUserId);
    }

    public function triggeringRecord(): PaymentRecord
    {
        return PaymentRecord::query()->findOrFail($this->paymentRecordId);
    }

    public function templateKey(): string
    {
        return 'payment_overdue';
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
            throw new AuthorizationException('The overdue payment notification recipient is invalid.');
        }

        $firm = app(FirmContext::class)->firm();
        $paymentRecord = PaymentRecord::query()
            ->with('obligation.client')
            ->findOrFail($this->paymentRecordId);

        return (new MailMessage)
            ->subject("Payment recorded as overdue for {$firm->name}")
            ->greeting("Hello {$notifiable->name}")
            ->line("A payment for {$paymentRecord->obligation->client->internal_code} was recorded as overdue.")
            ->line("Obligation: {$paymentRecord->obligation->obligation_type}.")
            ->action('Open work register', route('obligations.index'))
            ->line('Payment state is recorded separately from work and filing state. This platform does not move money.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
