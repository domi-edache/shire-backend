<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Run;
use App\Services\RunStateService;
use Illuminate\Http\Request;

class RunStatusController extends Controller
{
    protected $runStateService;

    public function __construct(RunStateService $runStateService)
    {
        $this->runStateService = $runStateService;
    }

    /**
     * Update run status.
     */
    public function update(Request $request, Run $run)
    {
        // Authorization: Only the run owner can update status
        if ($request->user()->id !== $run->user_id) {
            return response()->json([
                'message' => 'Unauthorized. Only the run owner can update status.'
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:heading_back,arrived,completed',
        ]);

        // Update status using service
        $updatedRun = $this->runStateService->updateStatus($run, $validated['status']);

        // Load relationships for resource
        $updatedRun->load(['user', 'items']);

        // Set a default distance string for the response
        $updatedRun->distance_string = "Updated";

        return new \App\Http\Resources\RunResource($updatedRun);
    }
}
