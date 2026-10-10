<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * SAMPLE stock and recipes so the stock checks and low-stock alerts can be tried.
 * Replace the quantities with the kitchen's real figures.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        // name => [stock, unit, minimum]
        $ingredients = [
            'Pork Liempo' => [20, 'kg', 5],
            'Chicken' => [25, 'kg', 8],
            'Shrimp' => [10, 'kg', 3],
            'Bangus (Milkfish)' => [8, 'kg', 3],
            'Squid' => [1.5, 'kg', 2], // starts low on purpose so the alert shows
            'Beef Shank' => [12, 'kg', 4],
            'Potatoes' => [30, 'kg', 10],
            'Cheese Sauce' => [6, 'L', 2],
            'Ground Beef' => [5, 'kg', 2],
            'Mango' => [4, 'kg', 1.5],
        ];

        $models = [];
        foreach ($ingredients as $name => [$stock, $unit, $min]) {
            $models[$name] = Ingredient::updateOrCreate(
                ['name' => $name],
                ['current_stock' => $stock, 'unit' => $unit, 'min_threshold' => $min]
            );
        }

        // menu item => [ingredient => quantity per serving]
        $recipes = [
            'Manila Flight' => ['Pork Liempo' => 0.5, 'Chicken' => 0.6],
            'Palawan Flight' => ['Bangus (Milkfish)' => 0.5, 'Shrimp' => 0.4],
            'Tagaytay Bulalo' => ['Beef Shank' => 0.5],
            'Vigan Sinigang na Lechon Kawali' => ['Pork Liempo' => 0.3],
            'Sampaloc Hipon Sinigang' => ['Shrimp' => 0.25],
            'Grilled Stuffed Squid' => ['Squid' => 0.3],
            'Dina Dakan Pork' => ['Pork Liempo' => 0.25],
            'Kyoto Sweet Glazed Wings' => ['Chicken' => 0.4],
            'Brussels Special Cheesy Beef Fries' => ['Potatoes' => 0.25, 'Cheese Sauce' => 0.1, 'Ground Beef' => 0.06],
            'Paris Classic Salted Fries' => ['Potatoes' => 0.2],
            'Premium Fruit Cabin Shakes' => ['Mango' => 0.15],
        ];

        foreach ($recipes as $itemName => $lines) {
            $item = MenuItem::where('name', $itemName)->first();

            if ($item === null) {
                continue;
            }

            $sync = [];
            foreach ($lines as $ingredientName => $quantity) {
                $sync[$models[$ingredientName]->id] = ['quantity' => $quantity];
            }

            $item->ingredients()->sync($sync);
        }
    }
}
