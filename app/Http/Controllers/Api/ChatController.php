<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Run;
use App\Models\RunChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    /**
     * Get all chat messages for a run.
     */
    public function index(Run $run)
    {
        $chats = $run->chats()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => $chats
        ]);
    }

    /**
     * Store a new chat message.
     */
    public function store(Request $request, Run $run)
    {
        $validated = $request->validate([
            'message' => 'nullable|string',
            'image' => 'nullable|image|max:5120', // 5MB max
        ]);

        $imagePath = null;

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('chat_images', $imageName, 'public');
        }

        // Create chat message
        $chat = RunChat::create([
            'run_id' => $run->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'] ?? null,
            'image_path' => $imagePath,
            'is_system_message' => false,
        ]);

        // Load user relationship
        $chat->load('user');

        return response()->json([
            'data' => $chat
        ], 201);
    }
}
