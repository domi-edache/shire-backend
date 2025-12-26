<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "--- Testing Postcode Geocoding ---\n";

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
]);

// 1. Create a user for testing
$user = User::factory()->create([
    'name' => 'Geocode Test',
    'email' => 'geocode_' . uniqid() . '@example.com',
    'handle' => 'geocode_' . uniqid(),
]);

echo "Created test user ID: {$user->id}\n";

// 1.5 Test socialLogin (Avatar URL Storage)
echo "\nTesting socialLogin (Avatar URL Storage)...\n";
$socialEmail = 'social_' . uniqid() . '@example.com';
$avatarUrl = 'https://example.com/social_avatar.jpg';

$response = app()->call('App\Http\Controllers\Api\AuthController@socialLogin', [
    'request' => Illuminate\Http\Request::create('/api/auth/social', 'POST', [
        'email' => $socialEmail,
        'provider' => 'google',
        'provider_id' => 'google_' . uniqid(),
        'name' => 'Social User',
        'avatar_url' => $avatarUrl,
        'token' => 'mock_token',
    ])
]);

$data = json_decode($response->getContent(), true);
$socialUser = User::find($data['user']['id']);

if ($socialUser->avatar_url === $avatarUrl) {
    echo "SUCCESS: Social avatar URL saved: {$socialUser->avatar_url}\n";
} else {
    echo "FAILURE: Social avatar URL not saved. Expected: {$avatarUrl}, Got: {$socialUser->avatar_url}\n";
}

// 2. Mock a request to onboarding
$postcode = 'SW1A 1AA'; // Buckingham Palace
$deviceLat = 51.5014;
$deviceLng = -0.1419;
$avatarUrl = 'https://example.com/avatar.jpg';
echo "Testing with postcode: {$postcode} and device location: {$deviceLat}, {$deviceLng}\n";

try {
    $request = Illuminate\Http\Request::create('/api/onboarding', 'POST', [
        'name' => 'Geocode Test',
        'handle' => $user->handle,
        'postcode' => $postcode,
        'device_lat' => $deviceLat,
        'device_lng' => $deviceLng,
        'avatar_url' => $avatarUrl,
    ]);
    $request->setUserResolver(fn() => $user);

    $response = app()->call('App\Http\Controllers\Api\OnboardingController@store', [
        'request' => $request
    ]);

    $data = json_decode($response->getContent(), true);

    if ($response->getStatusCode() === 200) {
        echo "SUCCESS: Onboarding request successful.\n";

        // Verify database
        $dbUser = DB::table('users')->select('*', DB::raw('ST_AsText(location) as location_text'), DB::raw('ST_AsText(signup_device_location) as device_location_text'))->where('id', $user->id)->first();

        if ($dbUser->location_text) {
            echo "SUCCESS: Main location (postcode) saved: {$dbUser->location_text}\n";
        } else {
            echo "FAILURE: Main location column is empty.\n";
        }

        if ($dbUser->device_location_text) {
            echo "SUCCESS: Shadow location (device) saved: {$dbUser->device_location_text}\n";
        } else {
            echo "FAILURE: Shadow location column is empty.\n";
        }
    } else {
        echo "FAILURE: Onboarding request failed with status " . $response->getStatusCode() . "\n";
        print_r($data);
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 3. Test with INVALID postcode
echo "\nTesting with INVALID postcode: INVALID\n";
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
        echo "SUCCESS: Invalid postcode correctly rejected with 422.\n";
        $data = json_decode($response->getContent(), true);
        echo "Error Message: " . $data['errors']['postcode'][0] . "\n";
    } else {
        echo "FAILURE: Invalid postcode should have returned 422, got " . $response->getStatusCode() . "\n";
    }
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "SUCCESS: ValidationException caught.\n";
    print_r($e->errors());
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "--- Verification Complete ---\n";
