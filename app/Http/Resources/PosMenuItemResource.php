<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosMenuItemResource extends JsonResource
{
    /** @return array<int, mixed> */
    public function toArray(Request $request): array
    {
        $ingredients = $this->ingredients;
        $stock = $ingredients->isEmpty()
            ? null
            : (int) $ingredients->map(function (Ingredient $ingredient): int {
                $usableStock = min((float) $ingredient->current_stock, $ingredient->usableStock());
                $remainingStock = max(0, $usableStock - (float) ($ingredient->reserved_quantity ?? 0));
                $requiredPerItem = $ingredient->rawQuantityForPortion((float) $ingredient->pivot->quantity);

                return (int) floor($remainingStock / $requiredPerItem + 0.0005);
            })->min();

        if (! $this->isOrderable()) {
            $stock = 0;
        }

        return [
            (string) $this->name,
            (float) $this->price,
            (string) ($this->category?->name ?? 'Uncategorized'),
            'k',
            $stock,
        ];
    }
}
