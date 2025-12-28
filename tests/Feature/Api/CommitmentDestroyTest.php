<?php

use App\Models\User;
use App\Models\Run;
use App\Models\RunItem;
use App\Models\RunCommitment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommitmentDestroyTest extends TestCase
{
    protected function setupUserLocation($user)
    {
        DB::statement("UPDATE users SET location = ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography WHERE id = ?", [$user->id]);
    }

    public function test_owner_can_leave_haul()
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $this->setupUserLocation($host);
        $this->setupUserLocation($participant);

        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'TestStore',
            'expires_at' => now()->addHour(),
            'status' => 'prepping',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        $item = RunItem::create([
            'run_id' => $run->id,
            'title' => 'Bulk Item',
            'type' => 'bulk_split',
            'cost' => 10,
            'units_total' => 5,
            'units_filled' => 2,
        ]);

        $commitment = RunCommitment::create([
            'run_item_id' => $item->id,
            'user_id' => $participant->id,
            'quantity' => 2,
            'status' => 'pending',
            'total_amount' => 20,
        ]);

        $response = $this->actingAs($participant)->deleteJson("/api/commitments/{$commitment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('run_commitments', ['id' => $commitment->id]);

        $item->refresh();
        $this->assertEquals(0, $item->units_filled);
    }

    public function test_host_can_kick_participant()
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $this->setupUserLocation($host);
        $this->setupUserLocation($participant);

        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'TestStore',
            'expires_at' => now()->addHour(),
            'status' => 'prepping',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        $item = RunItem::create([
            'run_id' => $run->id,
            'title' => 'Bulk Item',
            'type' => 'bulk_split',
            'cost' => 10,
            'units_total' => 5,
            'units_filled' => 2,
        ]);

        $commitment = RunCommitment::create([
            'run_item_id' => $item->id,
            'user_id' => $participant->id,
            'quantity' => 2,
            'status' => 'pending',
            'total_amount' => 20,
        ]);

        $response = $this->actingAs($host)->deleteJson("/api/commitments/{$commitment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('run_commitments', ['id' => $commitment->id]);
    }

    public function test_host_cannot_kick_themselves()
    {
        $host = User::factory()->create();
        $this->setupUserLocation($host);

        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'TestStore',
            'expires_at' => now()->addHour(),
            'status' => 'prepping',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        $item = RunItem::create([
            'run_id' => $run->id,
            'title' => 'Bulk Item',
            'type' => 'bulk_split',
            'cost' => 10,
            'units_total' => 5,
            'units_filled' => 1,
        ]);

        $hostCommitment = RunCommitment::create([
            'run_item_id' => $item->id,
            'user_id' => $host->id,
            'quantity' => 1,
            'status' => 'confirmed',
            'total_amount' => 0,
        ]);

        $response = $this->actingAs($host)->deleteJson("/api/commitments/{$hostCommitment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('run_commitments', ['id' => $hostCommitment->id]);
    }

    public function test_random_user_cannot_delete_commitment()
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $randomUser = User::factory()->create();
        $this->setupUserLocation($host);
        $this->setupUserLocation($participant);
        $this->setupUserLocation($randomUser);

        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'TestStore',
            'expires_at' => now()->addHour(),
            'status' => 'prepping',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        $item = RunItem::create([
            'run_id' => $run->id,
            'title' => 'Bulk Item',
            'type' => 'bulk_split',
            'cost' => 10,
            'units_total' => 5,
            'units_filled' => 2,
        ]);

        $commitment = RunCommitment::create([
            'run_item_id' => $item->id,
            'user_id' => $participant->id,
            'quantity' => 2,
            'status' => 'pending',
            'total_amount' => 20,
        ]);

        $response = $this->actingAs($randomUser)->deleteJson("/api/commitments/{$commitment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('run_commitments', ['id' => $commitment->id]);
    }
}
