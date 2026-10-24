<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Ingredient extends Model
{
    protected $fillable = [
        'name',
        'barcode',
        'category',
        'current_stock',
        'unit',
        'unit_cost',
        'min_threshold',
        'yield_loss_percent',
        'manual_override',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'min_threshold' => 'decimal:3',
            'yield_loss_percent' => 'decimal:2',
            'manual_override' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function orderReservations(): HasMany
    {
        return $this->hasMany(OrderIngredientReservation::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(InventoryPackage::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(InventoryStockLot::class);
    }

    public function preparationRecipe(): HasOne
    {
        return $this->hasOne(PreparationRecipe::class, 'output_ingredient_id');
    }

    public function preparationRecipeLines(): HasMany
    {
        return $this->hasMany(PreparationRecipeLine::class);
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'ingredient_menu_item', 'ingredient_id', 'menu_item_id')
            ->withPivot('quantity');
    }

    public function scopeUsedByCurrentMenu(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereHas('menuItems', fn (Builder $items) => $items
                ->where('is_active', true)
                ->whereNull('archived_at'))
                ->orWhereHas('preparationRecipeLines')
                ->orWhereHas('preparationRecipe');
        });
    }

    public function isLowStock(): bool
    {
        return ! $this->manual_override && $this->usableStock() <= (float) $this->min_threshold;
    }

    public function rawQuantityForPortion(float $portionQuantity): float
    {
        $yieldLoss = (float) $this->yield_loss_percent;
        if ($portionQuantity < 0 || $yieldLoss < 0 || $yieldLoss >= 100) {
            throw new \DomainException("Invalid recipe quantity or yield loss for {$this->name}.");
        }

        $rawQuantity = $portionQuantity / (1 - ($yieldLoss / 100));

        return ceil(($rawQuantity * 1000) - 1.0e-9) / 1000;
    }

    public function scopeWithUsableStock(Builder $query): Builder
    {
        if (! Schema::hasTable('inventory_stock_lots')) {
            return $query->addSelect('ingredients.*', 'ingredients.current_stock as usable_stock');
        }

        return $query->withSum([
            'stockLots as usable_stock' => fn ($lots) => $lots
                ->where('remaining_quantity', '>', 0)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today())),
        ], 'remaining_quantity');
    }

    public function usableStock(): float
    {
        if (! Schema::hasTable('inventory_stock_lots')) {
            return (float) $this->current_stock;
        }

        $lotStock = array_key_exists('usable_stock', $this->getAttributes())
            ? (float) $this->getAttribute('usable_stock')
            : (float) $this->stockLots()
                ->where('remaining_quantity', '>', 0)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
                ->sum('remaining_quantity');

        return min((float) $this->current_stock, $lotStock);
    }
}
