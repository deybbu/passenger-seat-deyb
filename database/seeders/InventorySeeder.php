<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Starter ingredient recipes and opening stock based on the current menu.
 *
 * Recipe quantities are estimates from menu descriptions and must be checked
 * against the kitchen's actual portions. Opening stock is 10 of each
 * ingredient in its listed unit (kg, L, or pcs).
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Chicken' => ['kg', 'Meat'],
            'Pork liempo' => ['kg', 'Meat'],
            'Pork belly' => ['kg', 'Meat'],
            'Lechon kawali' => ['kg', 'Meat'],
            'Pork barbecue' => ['kg', 'Meat'],
            'Pork sisig' => ['kg', 'Meat'],
            'Vigan longganisa' => ['kg', 'Meat'],
            'Beef' => ['kg', 'Meat'],
            'Beef shank' => ['kg', 'Meat'],
            'Bangus' => ['kg', 'Seafood'],
            'Shrimp' => ['kg', 'Seafood'],
            'Squid' => ['kg', 'Seafood'],
            'Salmon' => ['kg', 'Seafood'],
            'Shanghai filling' => ['kg', 'Meat'],
            'Lumpia wrappers' => ['pcs', 'Dry goods'],
            'Jasmine rice' => ['kg', 'Dry goods'],
            'Eggplant' => ['kg', 'Produce'],
            'Okra' => ['kg', 'Produce'],
            'Ensalada vegetables' => ['kg', 'Produce'],
            'Tomatoes' => ['kg', 'Produce'],
            'Onions' => ['kg', 'Produce'],
            'Bell pepper' => ['kg', 'Produce'],
            'Potatoes' => ['kg', 'Produce'],
            'Mushrooms' => ['kg', 'Produce'],
            'Avocado' => ['kg', 'Produce'],
            'Mango' => ['kg', 'Produce'],
            'Strawberries' => ['kg', 'Produce'],
            'Watermelon' => ['kg', 'Produce'],
            'Melon' => ['kg', 'Produce'],
            'Banana' => ['kg', 'Produce'],
            'Corn kernels' => ['kg', 'Produce'],
            'Red egg' => ['pcs', 'Dairy and eggs'],
            'Salted egg' => ['pcs', 'Dairy and eggs'],
            'Fried eggplant coating' => ['kg', 'Dry goods'],
            'Cooking oil' => ['L', 'Pantry'],
            'Sinigang mix' => ['kg', 'Pantry'],
            'Miso paste' => ['kg', 'Pantry'],
            'Kare-kare sauce' => ['kg', 'Pantry'],
            'Laing' => ['kg', 'Pantry'],
            'Garlic' => ['kg', 'Produce'],
            'Pasta' => ['kg', 'Dry goods'],
            'Bacon' => ['kg', 'Meat'],
            'Ham' => ['kg', 'Meat'],
            'White sauce' => ['L', 'Dairy and eggs'],
            'Cheese' => ['kg', 'Dairy and eggs'],
            'Salsa' => ['kg', 'Produce'],
            'Iced tea mix' => ['kg', 'Pantry'],
            'Soda' => ['can', 'Drinks'],
            'Beer' => ['pcs', 'Drinks'],
            'Cola' => ['L', 'Drinks'],
            'Grenadine syrup' => ['L', 'Drinks'],
            'Lemon-lime soda' => ['L', 'Drinks'],
            'Orange juice' => ['L', 'Drinks'],
            'Milo powder' => ['kg', 'Drinks'],
            'Milk' => ['L', 'Dairy and eggs'],
            'Avocado shake base' => ['L', 'Drinks'],
            'Mango shake base' => ['L', 'Drinks'],
            'Matcha powder' => ['kg', 'Drinks'],
            'Strawberry shake base' => ['L', 'Drinks'],
            'Watermelon shake base' => ['L', 'Drinks'],
            'Melon shake base' => ['L', 'Drinks'],
            'Ice' => ['kg', 'Pantry'],
            'Mais con yelo mix' => ['kg', 'Dessert'],
            'Halo-halo mix' => ['kg', 'Dessert'],
            'Brownie' => ['kg', 'Dessert'],
            'Ice cream' => ['L', 'Dessert'],
            'Bread' => ['pcs', 'Dessert'],
            'Chocnut' => ['kg', 'Dessert'],
            'Biscoff' => ['kg', 'Dessert'],
            'Yema' => ['kg', 'Dessert'],
            'Ube-gabi' => ['kg', 'Dessert'],
            'Pandan' => ['kg', 'Dessert'],
        ];

        // Menu item => ingredient => estimated quantity used per order.
        $recipes = [
            'FLT 001' => ['Pork liempo' => .15, 'Chicken' => .15, 'Shanghai filling' => .08, 'Lumpia wrappers' => 2, 'Eggplant' => .10, 'Red egg' => 1, 'Ensalada vegetables' => .10, 'Jasmine rice' => .20, 'Cooking oil' => .03],
            'FLT 002' => ['Pork liempo' => .15, 'Shrimp' => .12, 'Shanghai filling' => .08, 'Lumpia wrappers' => 2, 'Eggplant' => .10, 'Red egg' => 1, 'Ensalada vegetables' => .10, 'Jasmine rice' => .20, 'Cooking oil' => .03],
            'FLT 005' => ['Pork barbecue' => .15, 'Squid' => .15, 'Shanghai filling' => .08, 'Lumpia wrappers' => 2, 'Eggplant' => .10, 'Red egg' => 1, 'Ensalada vegetables' => .10, 'Jasmine rice' => .20, 'Cooking oil' => .03],
            'FLT 006' => ['Pork barbecue' => .15, 'Bangus' => .15, 'Shanghai filling' => .08, 'Lumpia wrappers' => 2, 'Eggplant' => .10, 'Red egg' => 1, 'Ensalada vegetables' => .10, 'Jasmine rice' => .20, 'Cooking oil' => .03],
            'Bacolod' => ['Chicken' => .20, 'Ensalada vegetables' => .10, 'Jasmine rice' => .20],
            'Cebu' => ['Pork liempo' => .20, 'Ensalada vegetables' => .10, 'Jasmine rice' => .20],
            'Dagupan' => ['Bangus' => .20, 'Ensalada vegetables' => .10, 'Jasmine rice' => .20, 'Cooking oil' => .02],
            'Ilocos' => ['Lechon kawali' => .20, 'Ensalada vegetables' => .10, 'Jasmine rice' => .20],
            'Roxas' => ['Squid' => .20, 'Ensalada vegetables' => .10, 'Jasmine rice' => .20],
            'Batanes' => ['Chicken' => .25, 'Pork liempo' => .20, 'Squid' => .20, 'Shrimp' => .15, 'Sinigang mix' => .03, 'Red egg' => 2, 'Okra' => .10, 'Eggplant' => .10, 'Ensalada vegetables' => .15, 'Jasmine rice' => .50, 'Iced tea mix' => .08],
            'Bohol' => ['Bangus' => .20, 'Shrimp' => .15, 'Lechon kawali' => .20, 'Red egg' => 1, 'Okra' => .10, 'Eggplant' => .10, 'Ensalada vegetables' => .12, 'Jasmine rice' => .40, 'Iced tea mix' => .08],
            'Caticlan' => ['Bangus' => .20, 'Shrimp' => .15, 'Squid' => .20, 'Pork sisig' => .18, 'Red egg' => 2, 'Okra' => .10, 'Eggplant' => .10, 'Ensalada vegetables' => .15, 'Jasmine rice' => .50, 'Iced tea mix' => .08, 'Cooking oil' => .04],
            'CHOOSE PH' => ['Chicken' => .30, 'Pork liempo' => .25, 'Lechon kawali' => .25, 'Squid' => .20, 'Bangus' => .20, 'Shrimp' => .15, 'Shanghai filling' => .15, 'Lumpia wrappers' => 4, 'Pork barbecue' => .15, 'Okra' => .15, 'Eggplant' => .15, 'Salted egg' => 1, 'Ensalada vegetables' => .20, 'Jasmine rice' => .80, 'Iced tea mix' => .15, 'Cooking oil' => .05],
            'Davao' => ['Chicken' => .25, 'Pork liempo' => .20, 'Squid' => .20, 'Red egg' => 1, 'Okra' => .10, 'Eggplant' => .10, 'Ensalada vegetables' => .15, 'Jasmine rice' => .50, 'Iced tea mix' => .08],
            'Manila' => ['Chicken' => .25, 'Pork liempo' => .20, 'Lechon kawali' => .20, 'Red egg' => 1, 'Okra' => .10, 'Eggplant' => .10, 'Ensalada vegetables' => .15, 'Jasmine rice' => .50, 'Iced tea mix' => .08],
            'Palawan' => ['Bangus' => .20, 'Squid' => .20, 'Shrimp' => .15, 'Red egg' => 1, 'Okra' => .10, 'Eggplant' => .10, 'Ensalada vegetables' => .15, 'Jasmine rice' => .50, 'Iced tea mix' => .08],
            'Pilipinas' => ['Chicken' => .35, 'Pork liempo' => .30, 'Lechon kawali' => .30, 'Bangus' => .25, 'Squid' => .25, 'Shrimp' => .20, 'Salmon' => .20, 'Miso paste' => .04, 'Red egg' => 2, 'Okra' => .15, 'Eggplant' => .15, 'Ensalada vegetables' => .20, 'Jasmine rice' => .80, 'Iced tea mix' => .15],
            'Siargo' => ['Chicken' => .25, 'Pork liempo' => .20, 'Lechon kawali' => .20, 'Beef shank' => .25, 'Red egg' => 2, 'Okra' => .10, 'Eggplant' => .10, 'Ensalada vegetables' => .15, 'Jasmine rice' => .60, 'Iced tea mix' => .08],
            'WOW PH' => ['Chicken' => .40, 'Pork liempo' => .35, 'Lechon kawali' => .35, 'Squid' => .25, 'Bangus' => .25, 'Shrimp' => .20, 'Shanghai filling' => .20, 'Lumpia wrappers' => 5, 'Pork barbecue' => .20, 'Pork sisig' => .20, 'Salmon' => .20, 'Miso paste' => .04, 'Okra' => .20, 'Eggplant' => .20, 'Beef' => .25, 'Kare-kare sauce' => .10, 'Laing' => .15, 'Salted egg' => 1, 'Ensalada vegetables' => .25, 'Jasmine rice' => 1.00, 'Iced tea mix' => .20, 'Cooking oil' => .07],
            'Luzviminda' => ['Chicken' => .30, 'Pork liempo' => .25, 'Lechon kawali' => .25, 'Bangus' => .20, 'Squid' => .20, 'Shrimp' => .15, 'Red egg' => 2, 'Okra' => .15, 'Eggplant' => .15, 'Ensalada vegetables' => .20, 'Jasmine rice' => .70, 'Iced tea mix' => .15],
            'Sampaloc' => ['Shrimp' => .25, 'Sinigang mix' => .04, 'Tomatoes' => .10, 'Onions' => .05],
            'Zambales' => ['Pork belly' => .25, 'Miso paste' => .04, 'Tomatoes' => .10, 'Onions' => .05],
            'Tagaytay' => ['Beef shank' => .35, 'Potatoes' => .10, 'Onions' => .05],
            'Mactan' => ['Pork barbecue' => .25, 'Cooking oil' => .02],
            'Laoag' => ['Pork belly' => .20, 'Onions' => .04],
            'Vigan' => ['Vigan longganisa' => .20, 'Cooking oil' => .02],
            'Pampanga' => ['Pork sisig' => .25, 'Onions' => .04, 'Cooking oil' => .02],
            'Angeles' => ['Beef shank' => .30, 'Kare-kare sauce' => .12, 'Potatoes' => .08],
            'Bicol (Laing)' => ['Laing' => .25],
            'Bicol (Laing with Lechon Kawali)' => ['Laing' => .20, 'Lechon kawali' => .18],
            'Mexico City' => ['Salsa' => .12, 'Beef' => .12, 'Onions' => .03, 'Cheese' => .05],
            'Paris' => ['Potatoes' => .20, 'Cooking oil' => .04],
            'Rome' => ['Pasta' => .12, 'White sauce' => .10, 'Bacon' => .04, 'Mushrooms' => .04, 'Ham' => .04],
            'Italy' => ['Pasta' => .12, 'Cooking oil' => .02, 'Garlic' => .01, 'Mushrooms' => .03, 'Bell pepper' => .03, 'Shrimp' => .08],
            'Iced Tea Solo' => ['Iced tea mix' => .025, 'Ice' => .20],
            'Iced Tea Pitcher' => ['Iced tea mix' => .10, 'Ice' => .60],
            'Soda in Can (Coca-Cola, Sprite, Royal)' => ['Soda' => 1],
            'Shirley Temple' => ['Lemon-lime soda' => .25, 'Grenadine syrup' => .02, 'Ice' => .15],
            'Roy Rogers' => ['Cola' => .25, 'Grenadine syrup' => .02, 'Ice' => .15],
            'Passenger Sunrise' => ['Orange juice' => .18, 'Grenadine syrup' => .02, 'Ice' => .15],
            'Milo Dinosaur' => ['Milo powder' => .04, 'Milk' => .25, 'Ice' => .15],
            'Sorsogon' => ['Avocado' => .12, 'Milk' => .20, 'Ice' => .15],
            'Guimaras' => ['Mango' => .12, 'Milk' => .20, 'Ice' => .15],
            'Okinawa' => ['Matcha powder' => .02, 'Milk' => .25, 'Ice' => .15],
            'Baguio City' => ['Strawberries' => .12, 'Milk' => .20, 'Ice' => .15],
            'Pangasinan' => ['Watermelon' => .15, 'Milk' => .15, 'Ice' => .15],
            'Taguig City' => ['Melon' => .15, 'Milk' => .15, 'Ice' => .15],
            'San Miguel Pale Pilsen' => ['Beer' => 1],
            'San Miguel Light/Flavored' => ['Beer' => 1],
            'Isabela' => ['Corn kernels' => .08, 'Milk' => .08, 'Ice' => .20],
            'Davao del Norte' => ['Banana' => .08, 'Milk' => .08, 'Ice' => .20],
            'San Fernando' => ['Halo-halo mix' => .15, 'Milk' => .05, 'Ice' => .20],
            'Belgium' => ['Brownie' => .12, 'Ice cream' => .08],
            'Strawberry' => ['Strawberries' => .08, 'Milk' => .05, 'Bread' => 2, 'Ice cream' => .06],
            'Melon' => ['Melon' => .08, 'Milk' => .05, 'Bread' => 2, 'Ice cream' => .06],
            'Pandan' => ['Pandan' => .02, 'Milk' => .05, 'Bread' => 2, 'Ice cream' => .06],
            'Chocnut' => ['Chocnut' => .03, 'Milk' => .05, 'Bread' => 2, 'Ice cream' => .06],
            'Yema & Cheese' => ['Yema' => .03, 'Cheese' => .02, 'Bread' => 2, 'Ice cream' => .06],
            'Coffee' => ['Biscoff' => .03, 'Milk' => .05, 'Bread' => 2, 'Ice cream' => .06],
            'Ube-Gabi' => ['Ube-gabi' => .04, 'Cheese' => .02, 'Bread' => 2, 'Ice cream' => .06],
        ];

        DB::transaction(function () use ($catalog, $recipes): void {
            $inventory = app(InventoryService::class);
            $ingredients = [];

            foreach ($catalog as $name => [$unit, $category]) {
                $ingredient = Ingredient::query()->firstOrCreate(
                    ['name' => $name],
                    [
                        'current_stock' => 0,
                        'unit' => $unit,
                        'unit_cost' => null,
                        'min_threshold' => 2,
                        'category' => $category,
                        'yield_loss_percent' => 0,
                        'manual_override' => false,
                    ],
                );
                $ingredients[$name] = $ingredient;

                // Set the requested opening quantity through inventory movements,
                // so stock lots and audit logs stay in sync with current_stock.
                $inventory->override($ingredient, 10, null, 'Requested menu setup stock');
            }

            foreach ($recipes as $itemName => $lines) {
                $item = MenuItem::query()->where('name', $itemName)->first();
                if ($item === null) {
                    continue;
                }

                $sync = [];
                foreach ($lines as $ingredientName => $quantity) {
                    $sync[$ingredients[$ingredientName]->id] = ['quantity' => $quantity];
                }

                $item->ingredients()->sync($sync);
            }
        });
    }
}
