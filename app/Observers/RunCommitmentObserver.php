<?php

namespace App\Observers;

use App\Models\RunActivity;
use App\Models\RunCommitment;

class RunCommitmentObserver
{
    /**
     * Handle the RunCommitment "created" event.
     */
    public function created(RunCommitment $commitment): void
    {
        RunActivity::create([
            'run_id' => $commitment->item->run_id,
            'user_id' => $commitment->user_id,
            'type' => 'user_joined',
            'metadata' => [
                'slots' => $commitment->quantity,
                'cost' => $commitment->total_amount,
            ],
        ]);

        // Notify Host: User Joined
        $commitment->load(['item.run.user', 'user']);
        $host = $commitment->item->run->user;
        $joiner = $commitment->user;

        if ($host && $joiner && $host->id !== $joiner->id) {
            $host->notify(new \App\Notifications\UserJoinedNotification(
                $commitment->item->run,
                $joiner->name
            ));
        }
    }

    /**
     * Handle the RunCommitment "updated" event.
     */
    public function updated(RunCommitment $commitment): void
    {
        if ($commitment->isDirty('status')) {
            $newStatus = $commitment->status;
            $commitment->load(['item.run.user', 'user']);

            if ($newStatus === 'paid_marked') {
                RunActivity::create([
                    'run_id' => $commitment->item->run_id,
                    'user_id' => $commitment->user_id,
                    'type' => 'payment_marked',
                ]);

                // Notify Host: Payment Sent
                $host = $commitment->item->run->user;
                if ($host) {
                    $host->notify(new \App\Notifications\PaymentSentNotification(
                        $commitment->item->run,
                        number_format($commitment->total_amount, 2),
                        $commitment->user->name
                    ));
                }

            } elseif ($newStatus === 'confirmed') {
                RunActivity::create([
                    'run_id' => $commitment->item->run_id,
                    'user_id' => $commitment->user_id,
                    'type' => 'payment_confirmed',
                ]);

                // Notify Participant: Payment Confirmed
                $participant = $commitment->user;
                if ($participant) {
                    $participant->notify(new \App\Notifications\PaymentConfirmedNotification(
                        $commitment->item->run
                    ));
                }
            }
        }
    }
}
