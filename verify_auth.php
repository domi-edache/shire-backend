<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "--- Testing Social Login ---\n";

// 1. Test New User (Needs Onboarding + Avatar Download)
echo "Testing New User...\n";
$email = 'newuser_' . uniqid() . '@example.com';
$avatarUrl = 'https://ui-avatars.com/api/?name=Test+User&size=128'; // Use a real URL for testing download

$response = app()->call('App\Http\Controllers\Api\AuthController@socialLogin', [
    'request' => Illuminate\Http\Request::create('/api/auth/social', 'POST', [
        'email' => $email,
        'provider' => 'google',
        'provider_id' => 'google_123',
        'name' => 'Test User',
        'avatar_url' => $avatarUrl,
        'token' => 'mock_token',
    ])
]);

$data = json_decode($response->getContent(), true);

if ($data['needs_onboarding'] === true) {
    echo "SUCCESS: New user needs onboarding.\n";
} else {
    echo "FAILURE: New user should need onboarding.\n";
}

if (str_contains($data['user']['profile_photo_url'], '/storage/avatars/')) {
    echo "SUCCESS: Avatar downloaded and local URL returned.\n";
} else {
    echo "FAILURE: Local avatar URL not found.\n";
    print_r($data['user']['profile_photo_url']);
}

// 2. Test Returning User (No Onboarding + Fallback Check)
echo "Testing Returning User...\n";
$user = $data['user'];
$userModel = User::find($user['id']);
$userModel->name = 'Test User';
$userModel->handle = 'test_handle_' . uniqid();
$userModel->postcode = 'SW1A 1AA';
$userModel->avatar_path = null; // Clear to test fallback
$userModel->save();

$response = app()->call('App\Http\Controllers\Api\AuthController@socialLogin', [
    'request' => Illuminate\Http\Request::create('/api/auth/social', 'POST', [
        'email' => $email,
        'provider' => 'google',
        'provider_id' => 'google_123',
        'name' => 'Test User',
        'token' => 'mock_token',
    ])
]);

$data = json_decode($response->getContent(), true);

if ($data['needs_onboarding'] === false) {
    echo "SUCCESS: Returning user does not need onboarding.\n";
} else {
    echo "FAILURE: Returning user should not need onboarding.\n";
}

if (str_contains($data['user']['profile_photo_url'], 'ui-avatars.com')) {
    echo "SUCCESS: Fallback to initials avatar working.\n";
} else {
    echo "FAILURE: Fallback avatar not working.\n";
    print_r($data['user']['profile_photo_url']);
}

echo "--- Verification Complete ---\n";
