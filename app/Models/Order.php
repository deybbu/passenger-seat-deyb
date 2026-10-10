<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'reference_number', 'type', 'table_number', 'customer_name', 'customer_phone', 'special_instructions',
        'total_amount', 'status', 'payment_status', 'payment_method', 'cashier_id', 'paid_at',
        'order_type', 'subtotal', 'total',
    ];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function getTotalAmountAttribute(): mixed
    {
        return $this->attributes['total_amount'] ?? $this->attributes['total'] ?? 0;
    }

    public function getTypeAttribute(): string
    {
        return $this->attributes['type'] ?? $this->attributes['order_type'] ?? 'dine_in';
    }

    public function isPaid(): bool
    {
        return ($this->payment_status ?? '') === 'paid';
    }

    public function canTransitionTo(string $to): bool
    {
        $allowed = [
            'pending' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'completed', 'cancelled'],
            'ready' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        return in_array($to, $allowed[$this->status] ?? [], true);
    }

    public function nextStatus(): ?string
    {
        return match ($this->status) {
            'pending' => 'preparing',
            'preparing' => 'ready',
            'ready' => 'completed',
            default => null,
        };
    }
}
