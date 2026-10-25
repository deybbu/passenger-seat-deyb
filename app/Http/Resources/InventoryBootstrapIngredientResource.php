<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBootstrapIngredientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => (string) $this->name,
            'cat' => (string) ($this->category ?: 'Other'),
            'unit' => (string) $this->unit,
            'stock' => (float) $this->current_stock,
            'min' => (float) $this->min_threshold,
            'vel' => (float) ($this->usage_per_day ?? 0),
            'cost' => (float) ($this->unit_cost ?? 0),
            'sup' => (string) ($this->preferred_supplier ?? ''),
            'exp' => (string) ($this->next_expiry ?? ''),
            'openingStock' => (float) ($this->opening_stock ?? $this->current_stock),
        ];
    }
}
