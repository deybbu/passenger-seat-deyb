<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('restaurant_tables')) {
            return;
        }

        // A fresh install is populated by RestaurantTableSeeder; this migration
        // only expands an already-seeded installation without duplicating fixtures.
        if (! DB::table('restaurant_tables')->where('table_number', '1')->exists()) {
            return;
        }

        for ($number = 13; $number <= 18; $number++) {
            $tableNumber = (string) $number;
            $exists = DB::table('restaurant_tables')->where('table_number', $tableNumber)->exists();

            if ($exists) {
                DB::table('restaurant_tables')->where('table_number', $tableNumber)->update([
                    'is_enabled' => true,
                    'updated_at' => now(),
                ]);
                continue;
            }

            do {
                $token = Str::random(64);
            } while (DB::table('restaurant_tables')->where('qr_token', $token)->exists());

            DB::table('restaurant_tables')->insert([
                'table_number' => $tableNumber,
                'name' => 'Table '.$tableNumber,
                'is_enabled' => true,
                'qr_token' => $token,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('restaurant_tables')) {
            DB::table('restaurant_tables')->whereIn('table_number', ['13', '14', '15', '16', '17', '18'])->delete();
        }
    }
};
