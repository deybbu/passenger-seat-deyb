<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\MenuImageUrl;

class CustomerMenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variants = $this->variants
            ->where('is_active', true)
            ->sortBy('id')
            ->values();

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'serving_label' => $variants->count() === 1
                && preg_match('/^Group \((\d+)-(\d+) servings\)$/', $variants->first()->name, $servingRange)
                    ? "Good for {$servingRange[1]} to {$servingRange[2]} persons"
                    : null,
            'price' => (float) $this->price,
            'category' => $this->category?->name ?? 'Uncategorized',
            'origin' => $this->origin ?? '',
            'reference_item' => [
                $this->name,
                $this->origin ?? '',
                $variants->isNotEmpty()
                    ? $variants
                        ->map(fn ($variant): array => [
                            $variant->name,
                            (float) $variant->price,
                        ])
                        ->all()
                    : (float) $this->price,
                $this->description ?? '',
            ],
            'variants' => $variants
                ->map(fn ($variant): array => [
                    'id' => (int) $variant->id,
                    'name' => $variant->name,
                    'price' => (float) $variant->price,
                ])
                ->all(),
            'modifiers' => $this->modifierGroups
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($group): array => [
                    'id' => (int) $group->id,
                    'name' => $group->name,
                    'required' => (bool) $group->is_required,
                    'options' => $group->options
                        ->where('is_active', true)
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($option): array => [
                            'id' => (int) $option->id,
                            'name' => $option->name,
                            'price_delta' => (float) $option->price_delta,
                        ])
                        ->all(),
                ])
                ->all(),
            'ingredients' => $this->ingredients
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->pluck('name')
                ->values()
                ->all(),
            'photo' => MenuImageUrl::forPath($this->image_path),
            'available' => (bool) $this->qr_available,
        ];
    }
}
