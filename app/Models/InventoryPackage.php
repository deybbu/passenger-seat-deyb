<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPackage extends Model
{
    protected $fillable = ['ingredient_id', 'barcode', 'label', 'package_quantity', 'is_active'];

    protected function casts(): array
    {
        return [
            'package_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(InventoryStockLot::class, 'package_id');
    }
}
