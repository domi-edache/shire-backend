<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Run;
use App\Models\RunItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Dom's location: POINT(0.018378 51.50833)
        $domLng = 0.018378;
        $domLat = 51.50833;

        $stores = ['Costco', 'Waitrose', 'Whole Foods', 'Lidl', 'Aldi', 'Tesco Superstore', 'Sainsbury\'s Local', 'M&S Foodhall'];
        $items = [
            ['title' => '20kg Beef Shin', 'cost' => 120, 'slots' => 4],
            ['title' => 'Organic Chicken Bulk', 'cost' => 80, 'slots' => 5],
            ['title' => 'Artisan Sourdough Batch', 'cost' => 45, 'slots' => 10],
            ['title' => 'Greek Olive Oil (5L)', 'cost' => 60, 'slots' => 3],
            ['title' => 'Premium Coffee Beans (3kg)', 'cost' => 75, 'slots' => 6],
            ['title' => 'Avocado Case (24ct)', 'cost' => 35, 'slots' => 8],
        ];

        $this->command->info("Generating 15 sample users and hauls...");

        for ($i = 1; $i <= 15; $i++) {
            $isNearby = $i <= 10;

            // Generate location
            if ($isNearby) {
                // Within ~1.5km (0.01 degrees is ~1.1km)
                $lng = $domLng + (mt_rand(-100, 100) / 10000);
                $lat = $domLat + (mt_rand(-100, 100) / 10000);
            } else {
                // Outside 5km
                $lng = $domLng + (mt_rand(100, 200) / 1000) * (mt_rand(0, 1) ? 1 : -1);
                $lat = $domLat + (mt_rand(100, 200) / 1000) * (mt_rand(0, 1) ? 1 : -1);
            }

            $name = "Sample User " . $i;
            $handle = "user_" . Str::lower(Str::random(5));

            $user = User::create([
                'name' => $name,
                'email' => "user{$i}_" . Str::random(5) . "@example.com",
                'password' => bcrypt('password'),
                'handle' => $handle,
                'postcode' => $isNearby ? 'E1 6AN' : 'SW1A 1AA',
                'address_line_1' => 'Sample Street ' . $i,
                'trust_score' => mt_rand(35, 50) / 10,
            ]);

            // Set location via DB statement to ensure PostGIS format
            DB::statement("UPDATE users SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?", [$lng, $lat, $user->id]);

            // Create a Haul (Run)
            $store = $stores[array_rand($stores)];
            $run = Run::create([
                'user_id' => $user->id,
                'store_name' => $store,
                'status' => 'live',
                'expires_at' => now()->addHours(mt_rand(2, 24)),
                'pickup_instructions' => "Meet at the main entrance of {$store}. I'll be wearing a green hat.",
                'runner_fee' => 0,
                'runner_fee_type' => 'free',
                'location' => DB::raw("ST_SetSRID(ST_MakePoint($lng, $lat), 4326)::geography"),
            ]);

            // Create a Bulk Split Item
            $itemData = $items[array_rand($items)];
            $totalSlots = $itemData['slots'];
            $filledSlots = mt_rand(0, $totalSlots - 1);

            RunItem::create([
                'run_id' => $run->id,
                'title' => $itemData['title'],
                'type' => 'bulk_split',
                'cost' => $itemData['cost'],
                'units_total' => $totalSlots,
                'units_filled' => $filledSlots,
                'status' => 'pending',
            ]);

            $this->command->info("Created user '{$handle}' with haul at '{$store}' (" . ($isNearby ? "NEARBY" : "FAR") . ")");
        }

        $this->command->info("\n✅ Done! 15 users and hauls generated.");
    }
}
