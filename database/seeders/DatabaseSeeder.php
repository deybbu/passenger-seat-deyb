<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MenuCategorySeeder::class,
            MenuSeeder::class,
            RestaurantTableSeeder::class,
            InventorySeeder::class,
            EventPackageSeeder::class,
        ]);
    }
}
