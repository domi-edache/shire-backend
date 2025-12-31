<?php

namespace App\Notifications;

use App\Models\Run;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification
{

    public function __construct(
        public Run $run
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Confirmed: ' . $this->run->store_name)
            ->line("The host has confirmed receipt of your payment for {$this->run->store_name}.")
            ->action('View Haul', url("/hauls/{$this->run->id}"))
            ->line('Thanks for using Shire!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payment Confirmed ✅',
            'body' => "{$this->run->user->name} confirmed your payment.",
            'run_id' => $this->run->id,
            'icon' => 'check_circle',
        ];
    }
}
