<?php

use App\Models\User;
use App\Models\Run;
use App\Models\RunItem;
use App\Models\RunCommitment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RunPrivacyTest extends TestCase
{
    protected function setupUserLocation($user)
    {
        DB::statement("UPDATE users SET location = ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography WHERE id = ?", [$user->id]);
    }

    public function test_host_can_see_all_details()
    {
        $host = User::factory()->create();
        $this->setupUserLocation($host);

        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'Privacy Store',
            'expires_at' => now()->addHour(),
            'status' => 'prepping',
            'pickup_instructions' => 'Secret Code: 1234',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        $response = $this->actingAs($host)->getJson("/api/hauls/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_host', true)
            ->assertJsonPath('data.pickup_instructions', 'Secret Code: 1234');
    }

    public function test_guest_cannot_see_sensitive_details()
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $this->setupUserLocation($host);
        $this->setupUserLocation($guest);

        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'Privacy Store',
            'expires_at' => now()->addHour(),
            'status' => 'prepping',
            'pickup_instructions' => 'Secret Code: 1234',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        $response = $this->actingAs($guest)->getJson("/api/hauls/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_host', false)
            ->assertJsonPath('data.pickup_instructions', null)
            ->assertJsonPath('data.fuzzy_location', 'Visible to participants');
    }

    public function test_confirmed_participant_can_see_details()
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $this->setupUserLocation($host);
        $this->setupUserLocation($participant);

        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'Privacy Store',
            'expires_at' => now()->addHour(),
            'status' => 'prepping',
            'pickup_instructions' => 'Secret Code: 1234',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        $item = RunItem::create([
            'run_id' => $run->id,
            'title' => 'Item 1',
            'type' => 'bulk_split',
            'cost' => 10,
            'units_total' => 1,
        ]);

        RunCommitment::create([
            'run_item_id' => $item->id,
            'user_id' => $participant->id,
            'quantity' => 1,
            'status' => 'confirmed',
            'payment_status' => 'confirmed',
            'total_amount' => 10,
        ]);

        $response = $this->actingAs($participant)->getJson("/api/hauls/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_host', false)
            ->assertJsonPath('data.my_commitment.status', 'confirmed')
            ->assertJsonPath('data.pickup_instructions', 'Secret Code: 1234');
    }

    public function test_unauthenticated_guest_can_view_haul_teaser()
    {
        $host = User::factory()->create(['postcode' => 'E8 1AA']);
        $this->setupUserLocation($host);

        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'Guest Test Store',
            'expires_at' => now()->addHour(),
            'status' => 'live',
            'pickup_instructions' => 'Secret Code: 1234',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        // No authentication (guest request)
        $response = $this->getJson("/api/hauls/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_guest', true)
            ->assertJsonPath('data.is_host', false)
            ->assertJsonPath('data.pickup_instructions', null)
            ->assertJsonPath('data.fuzzy_location', 'E8')
            ->assertJsonPath('data.store_name', 'Guest Test Store')
            ->assertJsonPath('data.distance', null);
    }
}
