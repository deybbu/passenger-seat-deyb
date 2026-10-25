<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'ingredient_id',
        'ordered_quantity',
        'received_quantity',
        'expected_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'expected_unit_cost' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(InventoryStockLot::class);
    }
}
