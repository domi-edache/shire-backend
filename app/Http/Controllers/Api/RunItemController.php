<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Run;
use App\Models\RunItem;
use Illuminate\Http\Request;

class RunItemController extends Controller
{
    /**
     * Store a newly created run item.
     */
    public function store(Request $request, Run $run)
    {
        // Security check: Only the run owner can add items
        if ($request->user()->id !== $run->user_id) {
            return response()->json([
                'message' => 'Unauthorized. Only the run owner can add items.'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'units_total' => 'required|integer|min:1',
        ]);

        // Create the run item
        $item = RunItem::create([
            'run_id' => $run->id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'cost' => $validated['cost'],
            'units_total' => $validated['units_total'],
            'units_filled' => 0,
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => $item
        ], 201);
    }
}
