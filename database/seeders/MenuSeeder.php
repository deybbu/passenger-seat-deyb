<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menu = [
            'Solo Boodle Flights' => [
                ['FLT 001', 'Grilled Liempo, Chicken Inasal, Shanghai, Fried Eggplant, Red Egg, Ensalada and Rice', 345],
                ['FLT 002', 'Grilled Liempo, Buttered Shrimp, Shanghai, Fried Eggplant, Red Egg, Ensalada and Rice', 345],
                ['FLT 005', 'Pork BBQ, Grilled Stuffed Squid, Shanghai, Fried Eggplant, Red Egg, Ensalada and Rice', 345],
                ['FLT 006', 'Pork BBQ, Fried Daing na Bangus, Shanghai, Fried Eggplant, Red Egg, Ensalada and Rice', 345],
            ],
            'Single Flights' => [
                ['Bacolod', 'Chicken Inasal, Ensalada, and Rice', 229],
                ['Cebu', 'Grilled Liempo, Ensalada, and Rice', 239],
                ['Dagupan', 'Daing na Bangus, Ensalada, and Rice', 269],
                ['Ilocos', 'Lechon Kawali, Ensalada, and Rice', 249],
                ['Roxas', 'Grilled Stuffed Squid, Ensalada, and Rice', 249],
            ],
            'International Flights' => [
                ['Batanes', 'Chicken Inasal, Grilled Liempo, Grilled Stuffed Squid, Sinigang na Hipon, 2 Red Eggs, Okra, Eggplant, Ensalada, Rice, and a Pitcher of Iced Tea (Good for 3 to 5 persons)', 1230],
                ['Bohol', 'Daing na Bangus, Buttered Shrimp, Lechon Kawali, Red Egg, Okra, Eggplant, Ensalada, Rice and a Pitcher of Iced Tea (Good for 2 to 3 persons)', 840],
                ['Caticlan', 'Daing na Bangus, Buttered Shrimp, Grilled Stuffed Squid, Sizzling Pork Sisig, 2 Red Eggs, Okra, Eggplant, Ensalada, Rice, and a Pitcher of Iced Tea (Good for 3 to 5 persons)', 1230],
                ['CHOOSE PH', 'Chicken Inasal, Liempo, Lechon Kawali, Grilled Stuffed Squid, Daing na Bangus, Buttered Shrimp, Shanghai, Pork BBQ, Boiled Okra, Fried Eggplant, Salted Egg, Ensalada, Rice and Pitchers of Iced Tea (Good for 5-8 persons)', 2050],
                ['Davao', 'Chicken Inasal, Grilled Liempo, Grilled Stuffed Squid, Red Egg, Okra, Eggplant, Ensalada, Rice and a Pitcher of Iced Tea (Good for 2 to 3 persons)', 840],
                ['Manila', 'Chicken Inasal, Grilled Liempo, Lechon Kawali, Red Egg, Okra, Eggplant, Ensalada, Rice and a Pitcher of Iced Tea (Good for 2 to 3 persons)', 840],
                ['Palawan', 'Daing na Bangus, Grilled Stuffed Squid, Buttered Shrimp, Red Egg, Okra, Eggplant, Ensalada, Rice and a Pitcher of Iced Tea (Good for 2 to 3 persons)', 840],
                ['Pilipinas', 'Chicken Inasal, Grilled Liempo, Lechon Kawali, Daing na Bangus, Grilled Stuffed Squid, Buttered Shrimp, Sinigang na Ulo ng Salmon sa Miso, 2 Red Eggs, Okra, Eggplant, Ensalada, Rice, and 2 Pitchers of Iced Tea (Good for 5 to 8 persons)', 2050],
                ['Siargo', 'Chicken Inasal, Grilled Liempo, Lechon Kawali, Bulalo, 2 Red Eggs, Okra, Eggplant, Ensalada, Rice, and a Pitcher of Iced Tea (Good for 3 to 5 persons)', 1235],
                ['WOW PH', 'Chicken Inasal, Liempo, Lechon Kawali, Grilled Stuffed Squid, Daing na Bangus, Buttered Shrimp, Shanghai, Pork BBQ, Pork Sisig, Sinigang na Ulo ng Salmon sa Miso, Boiled Okra, Fried Eggplant, Beef Kare-Kare, Laing, Salted Egg, Ensalada, Rice and Pitchers of Iced Tea (Good for 8-10 persons)', 3480],
                ['Luzviminda', 'Chicken Inasal, Grilled Liempo, Lechon Kawali, Daing na Bangus, Grilled Stuffed Squid, Buttered Shrimp, 2 Red Eggs, Okra, Eggplant, Ensalada, Rice, and 2 Pitchers of Iced Tea (Good for 5 to 7 persons)', 1625],
            ],
            'Ala Carte' => [
                ['Sampaloc', 'Sinigang na Hipon', 350], ['Zambales', 'Sinigang sa Miso', 345],
                ['Tagaytay', 'Bulalo', 499], ['Mactan', 'Pork Barbecue (5 pcs)', 280],
                ['Laoag', 'Dinadakan', 248], ['Vigan', 'Vigan Longganisa / Dish', 330],
                ['Pampanga', 'Sizzling Pork Sisig', 299], ['Angeles', 'Beef Bulalo Kare-Kare', 530],
                ['Bicol (Laing)', 'Laing', 200], ['Bicol (Laing with Lechon Kawali)', 'Laing with Lechon Kawali', 255],
            ],
            'International Flights - Appetizers' => [
                ['Mexico City', 'Homemade Salsa, Beef, Onions, and Torched Cheese on Top', 249],
                ['Paris', 'Salted Fries', 99],
                ['Rome', 'Creamy White Sauce Pasta with Bacon, Mushroom, and Ham', 279],
                ['Italy', 'Pasta in Olive Oil with Garlic, Red Chili Flakes, Mushroom, Bell Pepper and Shrimp', 0],
            ],
            'Drinks' => [
                ['Iced Tea Solo', '', 79], ['Iced Tea Pitcher', '', 150],
                ['Soda in Can (Coca-Cola, Sprite, Royal)', '', 85], ['Shirley Temple', 'Pink Illusion', 105],
                ['Roy Rogers', 'Cherry Cola', 105], ['Passenger Sunrise', 'Pink Juice', 105], ['Milo Dinosaur', 'Iced Milo', 120],
            ],
            'Shakes' => [
                ['Sorsogon', 'Avocado Shake', 199], ['Guimaras', 'Ripe Mango Shake', 199],
                ['Okinawa', 'Matcha Shake', 199], ['Baguio City', 'Strawberry Shake', 199],
                ['Pangasinan', 'Watermelon Shake', 199], ['Taguig City', 'Melon Shake', 199],
            ],
            'Beers' => [['San Miguel Pale Pilsen', '', 120], ['San Miguel Light/Flavored', '', 120]],
            'Pinoy Crushers' => [['Isabela', 'Mais Con Yelo', 99], ['Davao del Norte', 'Banan Con Yelo', 99], ['San Fernando', 'Halo-Halo', 109]],
            'Dessert' => [['Belgium', "Sizzling Brownies with Uncle D's Special Ice Cream", 189]],
            "Uncle D's Ice Cream Sandwich" => [
                ['Strawberry', '', 95], ['Melon', '', 95], ['Pandan', '', 95],
                ['Chocnut', 'Chocnut Ice Cream Sandwich with Chocnut Bits', 95],
                ['Yema & Cheese', 'Yema & Cheese Ice Cream Sandwich with Yema Bits', 95],
                ['Coffee', 'Coffee Ice Cream Sandwich with Biscoff Bits', 95],
                ['Ube-Gabi', 'Ube-Gabi Ice Cream Sandwich with Cheese', 95],
            ],
        ];

        DB::transaction(function () use ($menu): void {
            // Retire the prior menu and its recipe links while keeping inventory records intact.
            DB::table('ingredient_menu_item')->delete();
            MenuItem::query()->delete();
            MenuCategory::query()->delete();
            DB::table('categories')->delete();

            foreach ($menu as $categoryName => $items) {
                $category = MenuCategory::query()->create([
                    'name' => $categoryName,
                    'slug' => Str::slug($categoryName),
                    'is_active' => true,
                ]);

                foreach ($items as $index => [$name, $description, $price]) {
                    MenuItem::query()->create([
                        'menu_category_id' => $category->id,
                        'name' => $name,
                        'slug' => Str::slug($categoryName.'-'.$name),
                        'description' => $description !== '' ? $description : null,
                        'price' => $price,
                        'is_active' => true,
                        'is_available' => true,
                        'is_featured' => false,
                    ]);
                }
            }
        });
    }
}
