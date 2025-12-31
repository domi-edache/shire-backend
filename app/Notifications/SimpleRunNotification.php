<?php

namespace App\Notifications;

use App\Models\Run;
use Illuminate\Notifications\Notification;

class SimpleRunNotification extends Notification
{
    public function __construct(
        public Run $run,
        public string $title,
        public string $body
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'run_id' => $this->run->id,
            'icon' => 'info',
        ];
    }
}
