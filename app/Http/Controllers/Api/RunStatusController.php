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
            'status' => 'required|string|in:prepping,live,heading_back,arrived,completed',
        ]);

        // Prevent updating a completed run
        if ($run->status === 'completed') {
            return response()->json([
                'message' => 'This haul is already completed and cannot be updated.'
            ], 422);
        }

        // Define status order (statuses can only move forward)
        $statusOrder = ['prepping', 'live', 'heading_back', 'arrived', 'completed'];
        $currentIndex = array_search($run->status, $statusOrder);
        $newIndex = array_search($validated['status'], $statusOrder);

        // Prevent going backwards in status
        if ($newIndex !== false && $currentIndex !== false && $newIndex < $currentIndex) {
            $currentLabel = ucwords(str_replace('_', ' ', $run->status));
            $newLabel = ucwords(str_replace('_', ' ', $validated['status']));
            return response()->json([
                'message' => "Cannot change status from '{$currentLabel}' back to '{$newLabel}'. Statuses can only move forward."
            ], 422);
        }

        // Update status using service
        $updatedRun = $this->runStateService->updateStatus($run, $validated['status']);

        // Load relationships for resource
        $updatedRun->load(['user', 'items']);

        // Set a default distance string for the response
        $updatedRun->distance_string = "Updated";

        return new \App\Http\Resources\RunResource($updatedRun);
    }
}
