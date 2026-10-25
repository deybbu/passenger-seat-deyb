<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Order extends Model
{
    protected $fillable = [
        'reference_number', 'type', 'table_number', 'table_id', 'table_session_id', 'batch_number',
        'submission_token',
        'source',
        'customer_name', 'customer_phone', 'special_instructions',
        'total_amount', 'vat_amount', 'status', 'payment_status', 'payment_method', 'cashier_id', 'paid_at',
        'payment_reference', 'amount_tendered', 'order_type', 'subtotal', 'total', 'vat_exemption',
        'discount_type', 'discount_rate', 'discount_amount', 'discount_cardholder_name',
        'discount_id_number', 'discount_tin',
        'held_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vat_exemption' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'batch_number' => 'integer',
            'paid_at' => 'datetime',
            'held_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_order')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    public function scopeNotHeld(Builder $query): Builder
    {
        return $query->whereNull('held_at');
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

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
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
            'ready' => ['served', 'cancelled'],
            'served' => [],
            'cancelled' => [],
        ];

        return in_array($to, $allowed[$this->status] ?? [], true);
    }

    public function nextStatus(): ?string
    {
        return match ($this->status) {
            'pending' => 'preparing',
            'preparing' => 'ready',
            'ready' => 'served',
            default => null,
        };
    }
}
