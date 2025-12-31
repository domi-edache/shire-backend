<?php

namespace App\Notifications;

use App\Models\Run;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RunStatusChangedNotification extends Notification
{

    public function __construct(
        public Run $run,
        public string $status
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = str_replace('_', ' ', ucfirst($this->status));

        return (new MailMessage)
            ->subject("Haul Update: {$this->run->store_name} is {$statusLabel}")
            ->line("The status of the haul from {$this->run->store_name} has changed to: {$statusLabel}.")
            ->action('View Haul', url("/hauls/{$this->run->id}"))
            ->line('Check the app for details.');
    }

    public function toArray(object $notifiable): array
    {
        $host = $this->run->user->name;
        $store = $this->run->store_name;

        $body = match ($this->status) {
            'live' => "$host is shopping at $store now.",
            'heading_back' => "$host is heading back from $store.",
            'arrived' => "$host is back! Ready for pickup.",
            default => "$store run is now " . str_replace('_', ' ', $this->status),
        };

        return [
            'title' => 'Status Update 🚗',
            'body' => $body,
            'run_id' => $this->run->id,
            'icon' => 'info',
        ];
    }
}
