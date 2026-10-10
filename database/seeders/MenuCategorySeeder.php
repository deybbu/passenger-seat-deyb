<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Filipino Favorites', 'Group Meals', 'Boodle Fights', 'Beverages'] as $name) {
            MenuCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }
    }
}
