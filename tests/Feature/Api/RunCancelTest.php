<?php

use App\Models\User;
use App\Models\Run;
use App\Models\RunItem;
use App\Models\RunCommitment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RunCancelTest extends TestCase
{
    protected function setupUserLocation($user)
    {
        DB::statement("UPDATE users SET location = ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography WHERE id = ?", [$user->id]);
    }

    public function test_host_can_cancel_run_without_confirmed_payments()
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

        $response = $this->actingAs($host)->deleteJson("/api/hauls/{$run->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('runs', ['id' => $run->id]);

        // Ensure GET returns 404
        $this->actingAs($host)->getJson("/api/hauls/{$run->id}")->assertStatus(404);
    }

    public function test_host_cannot_cancel_run_with_confirmed_payments()
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

        // Participant with confirmed status
        RunCommitment::create([
            'run_item_id' => $item->id,
            'user_id' => $participant->id,
            'quantity' => 2,
            'status' => 'confirmed',
            'total_amount' => 20,
        ]);

        $response = $this->actingAs($host)->deleteJson("/api/hauls/{$run->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('runs', ['id' => $run->id]);
    }

    public function test_non_host_cannot_cancel_run()
    {
        $host = User::factory()->create();
        $randomUser = User::factory()->create();
        $this->setupUserLocation($host);
        $this->setupUserLocation($randomUser);

        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'TestStore',
            'expires_at' => now()->addHour(),
            'status' => 'prepping',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        $response = $this->actingAs($randomUser)->deleteJson("/api/hauls/{$run->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('runs', ['id' => $run->id]);
    }

    public function test_can_cancel_flag_is_true_when_no_confirmed_payments()
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

        $response = $this->actingAs($host)->getJson("/api/hauls/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_host', true)
            ->assertJsonPath('data.can_cancel', true);
    }

    public function test_can_cancel_flag_is_false_when_confirmed_payments_exist()
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

        RunCommitment::create([
            'run_item_id' => $item->id,
            'user_id' => $participant->id,
            'quantity' => 2,
            'status' => 'confirmed',
            'total_amount' => 20,
        ]);

        $response = $this->actingAs($host)->getJson("/api/hauls/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_host', true)
            ->assertJsonPath('data.can_cancel', false);
    }

    public function test_soft_deleted_runs_are_hidden_from_index()
    {
        $host = User::factory()->create();
        $this->setupUserLocation($host);

        // 1. Create a run and soft delete it
        $run = Run::create([
            'user_id' => $host->id,
            'store_name' => 'Deleted Store',
            'expires_at' => now()->addHour(),
            'status' => 'live',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);
        $run->delete();

        // 2. Create an active run
        Run::create([
            'user_id' => $host->id,
            'store_name' => 'Active Store',
            'expires_at' => now()->addHour(),
            'status' => 'live',
            'location' => DB::raw("ST_SetSRID(ST_MakePoint(-0.1278, 51.5074), 4326)::geography"),
        ]);

        $response = $this->actingAs($host)->getJson("/api/hauls?lat=51.5074&lng=-0.1278");

        $response->assertStatus(200)
            ->assertJsonMissing(['store_name' => 'Deleted Store'])
            ->assertJsonFragment(['store_name' => 'Active Store']);
    }
}
