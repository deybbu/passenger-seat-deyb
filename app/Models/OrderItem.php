<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'menu_item_id', 'item_name', 'name', 'quantity', 'price', 'line_total'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'quantity' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function getItemNameAttribute(): string
    {
        return $this->attributes['item_name'] ?? $this->attributes['name'] ?? '';
    }
}
