<?php

use App\Models\User;
use App\Models\Run;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RunStoreTest extends TestCase
{
    // We don't want to refresh database here if we are running in the main app env,
    // but ideally we should have a separate testing env.
    // For this environment, I'll just use a transaction or manual cleanup.

    protected function setupUserLocation($user)
    {
        DB::statement("UPDATE users SET location = ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography WHERE id = ?", [$user->id]);
    }

    public function test_create_run_with_memory_and_taking_requests()
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'default_pickup_instructions' => 'Old pickup',
            'default_payment_instructions' => 'Old payment',
        ]);
        $this->setupUserLocation($user);

        $response = $this->actingAs($user)
            ->postJson('/api/hauls', [
                'store_name' => 'Tesco',
                'expires_in' => 60,
                'is_taking_requests' => true,
                'pickup_instructions' => 'New pickup instructions',
                'payment_instructions' => 'New payment instructions',
                'pickup_image' => UploadedFile::fake()->image('pickup.jpg'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_taking_requests', true)
            ->assertJsonPath('data.pickup_instructions', 'New pickup instructions')
            ->assertJsonPath('data.payment_instructions', 'New payment instructions');

        // Verify "Memory" updated user profile
        $user->refresh();
        $this->assertEquals('New pickup instructions', $user->default_pickup_instructions);
        $this->assertEquals('New payment instructions', $user->default_payment_instructions);
        $this->assertNotNull($user->default_pickup_image_path);

        // Verify absolute URL
        $this->assertStringStartsWith('http', $response->json('data.pickup_image_url'));
        $this->assertStringContainsString('/storage/pickups/', $response->json('data.pickup_image_url'));
    }

    public function test_create_run_uses_memory_when_no_input_provided()
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('default.jpg')->store('pickups', 'public');

        $user = User::factory()->create([
            'default_pickup_instructions' => 'Saved pickup',
            'default_payment_instructions' => 'Saved payment',
            'default_pickup_image_path' => $path,
        ]);
        $this->setupUserLocation($user);

        $response = $this->actingAs($user)
            ->postJson('/api/hauls', [
                'store_name' => 'Waitrose',
                'expires_in' => 30,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.pickup_instructions', 'Saved pickup')
            ->assertJsonPath('data.payment_instructions', 'Saved payment')
            ->assertJsonPath('data.pickup_image_url', asset('storage/' . $path));
    }

    public function test_create_run_auto_commits_host()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->setupUserLocation($user);

        $response = $this->actingAs($user)
            ->postJson('/api/hauls', [
                'store_name' => 'Aldi',
                'expires_in' => 45,
                'anchor_title' => 'Bulk Apples',
                'anchor_total_cost' => 10.00,
                'anchor_slots' => 10,
                'host_slots' => 3,
                'pickup_image' => UploadedFile::fake()->image('pickup.jpg'),
            ]);

        $response->assertStatus(201);

        $runId = $response->json('data.id');
        $run = Run::with(['items.commitments', 'activities'])->find($runId);

        // Verify Host Commitment
        $item = $run->items->first();
        $this->assertEquals(3, $item->units_filled);

        $commitment = $item->commitments->first();
        $this->assertNotNull($commitment);
        $this->assertEquals($user->id, $commitment->user_id);
        $this->assertEquals(3, $commitment->quantity);
        $this->assertEquals('confirmed', $commitment->status);

        // Verify Activity
        $activity = $run->activities->where('type', 'host_auto_join')->first();
        $this->assertNotNull($activity);
        $this->assertEquals($user->id, $activity->user_id);
        $this->assertEquals(3, $activity->metadata['slots']);
    }
}
