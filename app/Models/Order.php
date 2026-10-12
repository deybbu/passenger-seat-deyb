<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'reference_number', 'type', 'table_number', 'table_session_id', 'submission_token',
        'customer_name', 'customer_phone', 'special_instructions',
        'total_amount', 'status', 'payment_status', 'payment_method', 'cashier_id', 'paid_at',
        'payment_reference', 'amount_tendered', 'order_type', 'subtotal', 'total',
        'discount_type', 'discount_rate', 'discount_amount', 'discount_cardholder_name',
        'discount_id_number', 'discount_tin',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function ingredientReservations(): HasMany
    {
        return $this->hasMany(OrderIngredientReservation::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
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
            'preparing' => ['ready', 'cancelled'],
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
