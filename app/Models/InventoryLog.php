<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    protected $fillable = [
        'ingredient_id', 'stock_lot_id', 'user_id', 'order_id', 'type', 'quantity', 'stock_before', 'stock_after', 'notes',
        'reason', 'cost_amount', 'stage',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'cost_amount' => 'decimal:4',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(InventoryStockLot::class, 'stock_lot_id');
    }
}
