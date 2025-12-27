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

        $this->command->info("Cleaning up old sample data...");
        // Delete users that start with 'user_' handle or have 'Sample User' in name
        $oldUsers = User::where('name', 'like', 'Sample User%')
            ->orWhere('handle', 'like', 'user_%')
            ->get();

        foreach ($oldUsers as $oldUser) {
            $oldUser->runs()->delete();
            $oldUser->delete();
        }

        $names = [
            ['name' => 'Alice Chen', 'handle' => 'alice_c'],
            ['name' => 'Marcus Thorne', 'handle' => 'mthorne'],
            ['name' => 'Sarah Jenkins', 'handle' => 'sarah_j'],
            ['name' => 'David O\'Connell', 'handle' => 'dave_oc'],
            ['name' => 'Priya Sharma', 'handle' => 'priya_s'],
            ['name' => 'Liam Gallagher', 'handle' => 'liam_g'],
            ['name' => 'Elena Rodriguez', 'handle' => 'elena_r'],
            ['name' => 'Kofi Mensah', 'handle' => 'kofi_m'],
            ['name' => 'Sophie Bennett', 'handle' => 'sophie_b'],
            ['name' => 'Jackson Reed', 'handle' => 'j_reed'],
            ['name' => 'Maya Patel', 'handle' => 'maya_p'],
            ['name' => 'Oliver Twist', 'handle' => 'oliver_t'],
            ['name' => 'Isabella Garcia', 'handle' => 'isabella_g'],
            ['name' => 'Noah Williams', 'handle' => 'noah_w'],
            ['name' => 'Emma Watson', 'handle' => 'emma_w'],
        ];

        $this->command->info("Generating 15 real-world users and hauls...");

        foreach ($names as $index => $data) {
            $i = $index + 1;
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

            $user = User::create([
                'name' => $data['name'],
                'email' => Str::slug($data['name']) . "_" . Str::random(3) . "@example.com",
                'password' => bcrypt('password'),
                'handle' => $data['handle'],
                'postcode' => $isNearby ? 'E1 6AN' : 'SW1A 1AA',
                'address_line_1' => 'Street ' . $i,
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

            $this->command->info("Created user '{$data['handle']}' with haul at '{$store}' (" . ($isNearby ? "NEARBY" : "FAR") . ")");
        }

        $this->command->info("\n✅ Done! 15 users and hauls generated with real names.");
    }
}
