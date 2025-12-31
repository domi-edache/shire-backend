<?php

use App\Models\Run;
use App\Http\Resources\RunResource;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$runId = 61;
$run = Run::with(['user', 'items'])->find($runId);

if (!$run) {
    echo "Run $runId not found.\n";
    exit;
}

echo "Run ID: {$run->id}\n";
echo "Pickup Image Path (DB): " . ($run->pickup_image_path ?? 'NULL') . "\n";
echo "Pickup Instructions (DB): " . ($run->pickup_instructions ?? 'NULL') . "\n";
echo "User ID: {$run->user_id}\n";

// Mock Auth
Auth::login($run->user);

$resource = new RunResource($run);
$json = $resource->response()->getData(true);

echo "Pickup Image URL (Resource): " . ($json['data']['pickup_image_url'] ?? 'NULL') . "\n";
echo "Pickup Instructions (Resource): " . ($json['data']['pickup_instructions'] ?? 'NULL') . "\n";
echo "Can See Details: " . ($json['data']['pickup_instructions'] !== null ? 'YES' : 'NO') . "\n";
