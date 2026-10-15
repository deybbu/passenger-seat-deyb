<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MenuItem extends Model
{
    use HasFactory;

    private const STOCK_EPSILON = 0.0005;

    protected $fillable = [
        'menu_category_id', 'name', 'slug', 'description', 'image_path',
        'price', 'is_active', 'is_available', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_menu_item', 'menu_item_id', 'ingredient_id')
            ->withPivot('quantity');
    }

    public function scopeWithStockAvailability(Builder $query): Builder
    {
        return $query->with(self::stockAvailabilityRelations());
    }

    public function hasSufficientIngredientStock(): bool
    {
        $ingredients = $this->relationLoaded('ingredients')
            ? $this->getRelation('ingredients')
            : collect();
        $needsAggregate = ! $this->relationLoaded('ingredients')
            || $ingredients->contains(
                fn (Ingredient $ingredient): bool => ! array_key_exists('reserved_quantity', $ingredient->getAttributes())
                    || ! array_key_exists('usable_stock', $ingredient->getAttributes())
            );

        if ($needsAggregate) {
            $this->load(self::stockAvailabilityRelations());
            $ingredients = $this->getRelation('ingredients');
        }

        return $ingredients->every(
            fn (Ingredient $ingredient): bool => min(
                (float) $ingredient->current_stock,
                (float) ($ingredient->usable_stock ?? 0),
            )
                - (float) ($ingredient->reserved_quantity ?? 0)
                + self::STOCK_EPSILON
                >= $ingredient->rawQuantityForPortion((float) $ingredient->pivot->quantity)
        );
    }

    public function isOrderable(): bool
    {
        return $this->is_active && $this->is_available && $this->hasSufficientIngredientStock();
    }

    private static function stockAvailabilityRelations(): array
    {
        return [
            'ingredients' => static fn ($query) => $query
                ->withSum([
                    'orderReservations as reserved_quantity' => static fn ($reservations) => $reservations
                        ->where('status', 'reserved'),
                ], 'quantity')
                ->withUsableStock(),
        ];
    }
}
