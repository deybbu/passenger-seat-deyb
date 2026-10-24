<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBootstrapRecipeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'n' => (string) $this->name,
            'price' => (float) $this->price,
            'ingredients' => $this->ingredients->map(fn ($ingredient): array => [
                'id' => (string) $ingredient->id,
                'q' => (float) $ingredient->pivot->quantity,
            ])->values()->all(),
        ];
    }
}
