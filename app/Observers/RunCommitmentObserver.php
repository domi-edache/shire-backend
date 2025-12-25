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
    }

    /**
     * Handle the RunCommitment "updated" event.
     */
    public function updated(RunCommitment $commitment): void
    {
        if ($commitment->isDirty('status')) {
            $newStatus = $commitment->status;

            if ($newStatus === 'paid_marked') {
                RunActivity::create([
                    'run_id' => $commitment->item->run_id,
                    'user_id' => $commitment->user_id,
                    'type' => 'payment_marked',
                ]);
            } elseif ($newStatus === 'confirmed') {
                RunActivity::create([
                    'run_id' => $commitment->item->run_id,
                    'user_id' => $commitment->user_id,
                    'type' => 'payment_confirmed',
                ]);
            }
        }
    }
}
