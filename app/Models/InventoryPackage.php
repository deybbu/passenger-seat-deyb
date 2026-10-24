<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryPackage extends Model
{
    protected $fillable = [
        'ingredient_id',
        'barcode',
        'manual_entry_code',
        'label',
        'package_quantity',
        'is_active',
        'created_by',
    ];

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

    public function receipt(): HasOne
    {
        return $this->hasOne(InventoryReceipt::class, 'package_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
