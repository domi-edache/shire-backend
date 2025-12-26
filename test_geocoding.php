<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "--- Testing Refined Postcode Geocoding ---\n";

// Mock Http responses for geocoding
Http::fake([
    'api.postcodes.io/postcodes/SW1A1AA' => Http::response([
        'status' => 200,
        'result' => [
            'latitude' => 51.50101,
            'longitude' => -0.141563,
        ]
    ], 200),
    'api.postcodes.io/postcodes/INVALID' => Http::response([
        'status' => 404,
        'error' => 'Postcode not found'
    ], 404),
    'api.postcodes.io/postcodes/FAIL*' => function () {
        throw new \Illuminate\Http\Client\ConnectionException("Connection failed");
    },
]);

// 1. Create a user for testing
$user = User::factory()->create([
    'name' => 'Geocode Test',
    'email' => 'geocode_' . uniqid() . '@example.com',
    'handle' => 'geocode_' . uniqid(),
]);

echo "Created test user ID: {$user->id}\n";

// 2. Test SUCCESS (Postcode Found)
echo "\n--- Scenario 1: SUCCESS (Postcode Found) ---\n";
$postcode = 'SW1A 1AA';
try {
    $request = Illuminate\Http\Request::create('/api/onboarding', 'POST', [
        'name' => 'Geocode Test',
        'handle' => $user->handle,
        'postcode' => $postcode,
    ]);
    $request->setUserResolver(fn() => $user);

    $response = app()->call('App\Http\Controllers\Api\OnboardingController@store', [
        'request' => $request
    ]);

    if ($response->getStatusCode() === 200) {
        $dbUser = DB::table('users')->select(DB::raw('ST_AsText(location) as location_text'))->where('id', $user->id)->first();
        echo "SUCCESS: Main location saved: {$dbUser->location_text}\n";
    } else {
        echo "FAILURE: Status " . $response->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 3. Test TYPO (404)
echo "\n--- Scenario 2: TYPO (404 Not Found) ---\n";
try {
    $request = Illuminate\Http\Request::create('/api/onboarding', 'POST', [
        'name' => 'Geocode Test',
        'handle' => $user->handle,
        'postcode' => 'INVALID',
    ]);
    $request->setUserResolver(fn() => $user);

    $response = app()->call('App\Http\Controllers\Api\OnboardingController@store', [
        'request' => $request
    ]);

    if ($response->getStatusCode() === 422) {
        $data = json_decode($response->getContent(), true);
        echo "SUCCESS: Correctly rejected with 422. Message: " . $data['errors']['postcode'][0] . "\n";
    } else {
        echo "FAILURE: Expected 422, got " . $response->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 4. Test SERVICE DOWN + DEVICE GPS
echo "\n--- Scenario 3: SERVICE DOWN + DEVICE GPS ---\n";
try {
    $request = Illuminate\Http\Request::create('/api/onboarding', 'POST', [
        'name' => 'Geocode Test',
        'handle' => $user->handle,
        'postcode' => 'FAIL1',
        'device_lat' => 52.0,
        'device_lng' => 0.5,
    ]);
    $request->setUserResolver(fn() => $user);

    $response = app()->call('App\Http\Controllers\Api\OnboardingController@store', [
        'request' => $request
    ]);

    if ($response->getStatusCode() === 200) {
        $dbUser = DB::table('users')->select(DB::raw('ST_AsText(location) as location_text'))->where('id', $user->id)->first();
        $data = json_decode($response->getContent(), true);
        echo "SUCCESS: Fallback to Device GPS: {$dbUser->location_text}\n";
        if (isset($data['warning'])) {
            echo "SUCCESS: Warning received: " . $data['warning'] . "\n";
        } else {
            echo "FAILURE: No warning received.\n";
        }
    } else {
        echo "FAILURE: Status " . $response->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 5. Test SERVICE DOWN + NO GPS
echo "\n--- Scenario 4: SERVICE DOWN + NO GPS ---\n";
try {
    $request = Illuminate\Http\Request::create('/api/onboarding', 'POST', [
        'name' => 'Geocode Test',
        'handle' => $user->handle,
        'postcode' => 'FAIL2',
    ]);
    $request->setUserResolver(fn() => $user);

    $response = app()->call('App\Http\Controllers\Api\OnboardingController@store', [
        'request' => $request
    ]);

    if ($response->getStatusCode() === 200) {
        $dbUser = DB::table('users')->select(DB::raw('ST_AsText(location) as location_text'))->where('id', $user->id)->first();
        $data = json_decode($response->getContent(), true);
        echo "SUCCESS: Fallback to Central London: {$dbUser->location_text}\n";
        if (isset($data['warning'])) {
            echo "SUCCESS: Warning received: " . $data['warning'] . "\n";
        } else {
            echo "FAILURE: No warning received.\n";
        }
    } else {
        echo "FAILURE: Status " . $response->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Verification Complete ---\n";
