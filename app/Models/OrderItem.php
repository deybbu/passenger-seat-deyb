<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_item_variant_id',
        'item_name',
        'name',
        'variant_name_snapshot',
        'category_name_snapshot',
        'modifier_snapshot',
        'notes',
        'quantity',
        'seat_number',
        'price',
        'line_total',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'seat_number' => 'integer',
            'modifier_snapshot' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class, 'menu_item_variant_id');
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function getItemNameAttribute(): string
    {
        return $this->attributes['item_name'] ?? $this->attributes['name'] ?? '';
    }
}
