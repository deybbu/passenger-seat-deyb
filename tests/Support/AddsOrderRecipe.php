<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Ingredient;
use App\Models\InventoryStockLot;
use App\Models\MenuItem;
use Illuminate\Support\Str;

trait AddsOrderRecipe
{
    protected function addOrderRecipe(MenuItem $item, string $name = 'Test stock'): Ingredient
    {
        $ingredient = Ingredient::query()->create([
            'name' => $name.' '.Str::lower(Str::random(6)),
            'current_stock' => 500,
            'unit' => 'kg',
            'unit_cost' => 10,
            'min_threshold' => 1,
        ]);
        InventoryStockLot::query()->create([
            'ingredient_id' => $ingredient->id,
            'expires_at' => today()->addYear(),
            'received_quantity' => 500,
            'remaining_quantity' => 500,
            'unit_cost' => 10,
            'receipt_token' => (string) Str::uuid(),
            'received_at' => now(),
        ]);
        $item->ingredients()->attach($ingredient->id, ['quantity' => 0.01]);

        return $ingredient;
    }
}
