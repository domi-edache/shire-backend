<?php

namespace App\Notifications;

use App\Models\Run;
use Illuminate\Notifications\Notification;

class UserJoinedNotification extends Notification
{

    public function __construct(
        public Run $run,
        public string $joinerName
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Neighbor Joined 👋',
            'body' => "{$this->joinerName} claimed a slot in your {$this->run->store_name} run.",
            'run_id' => $this->run->id,
            'icon' => 'person_add',
        ];
    }
}
