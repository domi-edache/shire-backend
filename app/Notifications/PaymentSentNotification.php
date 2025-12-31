<?php

namespace App\Notifications;

use App\Models\Run;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSentNotification extends Notification
{

    public function __construct(
        public Run $run,
        public string $amount,
        public string $payerName
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Received for ' . $this->run->store_name)
            ->line("{$this->payerName} has marked their payment of £{$this->amount} as sent.")
            ->action('View Haul', url("/hauls/{$this->run->id}"))
            ->line('Please confirm receipt in the app.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payment Sent 💸',
            'body' => "{$this->payerName} marked their share as paid.",
            'run_id' => $this->run->id,
            'icon' => 'payment',
        ];
    }
}
