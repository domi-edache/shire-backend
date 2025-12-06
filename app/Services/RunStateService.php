<?php

namespace App\Services;

use App\Models\Run;
use App\Models\RunChat;

class RunStateService
{
    /**
     * Update run status and create system notifications.
     * 
     * @param Run $run
     * @param string $newStatus
     * @return Run
     */
    public function updateStatus(Run $run, string $newStatus): Run
    {
        // Update the run status
        $run->status = $newStatus;
        $run->save();

        // Determine system message based on status
        $message = $this->getStatusMessage($newStatus);

        if ($message) {
            // Create system chat message
            RunChat::create([
                'run_id' => $run->id,
                'user_id' => null,
                'message' => $message,
                'is_system_message' => true,
            ]);

            // Trigger notification to buyers
            $notificationService = new NotificationService();
            $notificationService->notifyRunUpdate($run, $message);
        }

        return $run;
    }

    /**
     * Get the system message for a given status.
     * 
     * @param string $status
     * @return string|null
     */
    private function getStatusMessage(string $status): ?string
    {
        return match ($status) {
            'heading_back' => '⚡️ Status Update: The Runner is heading back!',
            'arrived' => '📍 Status Update: The Runner has arrived. Check pickup instructions.',
            default => null,
        };
    }
}
