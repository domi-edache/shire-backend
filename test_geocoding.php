<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "--- Testing Postcode Geocoding ---\n";

// 1. Create a user for testing
$user = User::factory()->create([
    'name' => 'Geocode Test',
    'email' => 'geocode_' . uniqid() . '@example.com',
    'handle' => 'geocode_' . uniqid(),
]);

echo "Created test user ID: {$user->id}\n";

// 2. Mock a request to onboarding
$postcode = 'SW1A 1AA'; // Buckingham Palace
echo "Testing with postcode: {$postcode}\n";

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

    $data = json_decode($response->getContent(), true);

    if ($response->getStatusCode() === 200) {
        echo "SUCCESS: Onboarding request successful.\n";

        // Verify database
        $dbUser = DB::table('users')->where('id', $user->id)->first();
        if ($dbUser->location) {
            echo "SUCCESS: Location column is populated.\n";
            // We can't easily read the binary location here without ST_AsText, 
            // but the fact it's not null is a good sign.
        } else {
            echo "FAILURE: Location column is empty.\n";
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
