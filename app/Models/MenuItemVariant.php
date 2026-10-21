<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItemVariant extends Model
{
    protected $fillable = [
        'menu_item_id', 'name', 'price', 'portion_factor', 'stock_quantity',
        'min_stock_quantity', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'portion_factor' => 'decimal:3',
            'stock_quantity' => 'decimal:3',
            'min_stock_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
