<?php

namespace App\Observers;

use App\Models\Run;
use App\Models\RunActivity;
use Illuminate\Support\Facades\Auth;

class RunObserver
{
    /**
     * Handle the Run "updated" event.
     */
    public function updated(Run $run): void
    {
        if ($run->isDirty('status')) {
            RunActivity::create([
                'run_id' => $run->id,
                'user_id' => Auth::id(),
                'type' => 'status_change',
                'metadata' => [
                    'old' => $run->getOriginal('status'),
                    'new' => $run->status,
                ],
            ]);
        }
    }
}
