<?php

use App\Models\User;
use App\Models\Run;
use App\Models\RunItem;
use App\Models\RunCommitment;
use App\Models\RunActivity;
use Illuminate\Support\Facades\Auth;

// 1. Setup: Get or create a user and a run
$user = User::first() ?? User::factory()->create();
Auth::login($user);

$run = Run::first() ?? Run::create([
    'user_id' => $user->id,
    'store_name' => 'Test Store',
    'status' => 'prepping',
    'expires_at' => now()->addHour(),
]);

$item = $run->items()->first() ?? $run->items()->create([
    'user_id' => $user->id,
    'name' => 'Test Item',
    'type' => 'bulk_split',
    'units_total' => 10,
    'units_filled' => 0,
]);

echo "--- Testing Activity Feed ---\n";

// 2. Test 'user_joined'
echo "Testing 'user_joined'...\n";
$commitment = RunCommitment::create([
    'run_item_id' => $item->id,
    'user_id' => $user->id,
    'quantity' => 2,
    'total_amount' => 20.00,
    'status' => 'pending',
]);

$activity = RunActivity::where('run_id', $run->id)->where('type', 'user_joined')->latest()->first();
if ($activity && $activity->metadata['slots'] == 2) {
    echo "SUCCESS: 'user_joined' activity logged.\n";
} else {
    echo "FAILURE: 'user_joined' activity NOT logged correctly.\n";
}

// 3. Test 'payment_marked'
echo "Testing 'payment_marked'...\n";
$commitment->update(['status' => 'paid_marked']);
$activity = RunActivity::where('run_id', $run->id)->where('type', 'payment_marked')->latest()->first();
if ($activity) {
    echo "SUCCESS: 'payment_marked' activity logged.\n";
} else {
    echo "FAILURE: 'payment_marked' activity NOT logged.\n";
}

// 4. Test 'payment_confirmed'
echo "Testing 'payment_confirmed'...\n";
$commitment->update(['status' => 'confirmed']);
$activity = RunActivity::where('run_id', $run->id)->where('type', 'payment_confirmed')->latest()->first();
if ($activity) {
    echo "SUCCESS: 'payment_confirmed' activity logged.\n";
} else {
    echo "FAILURE: 'payment_confirmed' activity NOT logged.\n";
}

// 5. Test 'status_change'
echo "Testing 'status_change'...\n";
$run->update(['status' => 'live']);
$activity = RunActivity::where('run_id', $run->id)->where('type', 'status_change')->latest()->first();
if ($activity && $activity->metadata['old'] == 'prepping' && $activity->metadata['new'] == 'live') {
    echo "SUCCESS: 'status_change' activity logged.\n";
} else {
    echo "FAILURE: 'status_change' activity NOT logged correctly.\n";
}

echo "--- Verification Complete ---\n";
