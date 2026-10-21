<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStockLot extends Model
{
    protected $fillable = [
        'ingredient_id',
        'package_id',
        'batch_number',
        'expires_at',
        'received_quantity',
        'remaining_quantity',
        'unit_cost',
        'receipt_token',
        'received_by',
        'received_at',
        'storage_zone',
        'purchase_order_item_id',
        'supplier_invoice_number',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'received_quantity' => 'decimal:3',
            'remaining_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'received_at' => 'datetime',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(InventoryPackage::class, 'package_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InventoryLog::class, 'stock_lot_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
