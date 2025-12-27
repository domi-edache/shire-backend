<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client(['base_uri' => 'http://localhost']);

try {
    // 1. Login to get token
    $response = $client->post('/api/auth/social', [
        'json' => [
            'email' => 'dom.test.xx@gmail.com',
            'name' => 'dom',
            'provider' => 'google',
            'provider_id' => '106601971350612188938',
            'token' => 'mock_token',
        ]
    ]);
    $data = json_decode($response->getBody(), true);
    $token = $data['token'];

    echo "Logged in. Token: " . substr($token, 0, 10) . "...\n";

    // 2. Create Haul
    $response = $client->post('/api/runs', [
        'headers' => [
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json',
        ],
        'json' => [
            'store_name' => 'Costco',
            'expires_in' => 60,
            'anchor_title' => '20kg Beef Shin',
            'anchor_total_cost' => 120,
            'anchor_slots' => 4,
        ]
    ]);

    echo "Haul created successfully!\n";
    print_r(json_decode($response->getBody(), true));

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        echo "Response: " . $e->getResponse()->getBody() . "\n";
    }
}
