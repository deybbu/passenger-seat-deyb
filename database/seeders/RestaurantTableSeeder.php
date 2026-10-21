<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

class RestaurantTableSeeder extends Seeder
{
    public function run(): void
    {
        for ($number = 1; $number <= 18; $number++) {
            RestaurantTable::firstOrCreate(
                ['table_number' => (string) $number],
                [
                    'name' => 'Table '.$number,
                    'is_enabled' => true,
                    'qr_token' => RestaurantTable::generateUniqueQrToken(),
                ],
            );
        }
    }
}
