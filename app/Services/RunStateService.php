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
        $message = $this->getStatusMessage($run, $newStatus);

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
     * @param Run $run
     * @param string $status
     * @return string|null
     */
    private function getStatusMessage(Run $run, string $status): ?string
    {
        $hostName = $run->user->name ?? 'The host';

        return match ($status) {
            'heading_back' => "⚡️ {$hostName} is heading back!",
            'arrived' => "📍 {$hostName} is back! Ready for pickup.",
            default => null,
        };
    }
}
