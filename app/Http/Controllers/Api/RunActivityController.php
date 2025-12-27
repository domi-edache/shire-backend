<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Run;
use App\Http\Resources\RunActivityResource;
use Illuminate\Http\Request;

class RunActivityController extends Controller
{
    /**
     * Display a listing of activities for a specific run.
     */
    public function index(Request $request, Run $run)
    {
        $activities = $run->activities()
            ->with('user')
            ->latest()
            ->paginate(20);

        return RunActivityResource::collection($activities);
    }
}
