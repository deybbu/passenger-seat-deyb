<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

class RestaurantTableSeeder extends Seeder
{
    public function run(): void
    {
        for ($number = 1; $number <= 12; $number++) {
            RestaurantTable::updateOrCreate(
                ['table_number' => (string) $number],
                [
                    'name' => 'Table '.$number,
                    'is_enabled' => false,
                    'qr_token' => RestaurantTable::query()
                        ->where('table_number', (string) $number)
                        ->value('qr_token') ?: RestaurantTable::generateUniqueQrToken(),
                ],
            );
        }
    }
}
