<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableSession extends Model
{
    protected $fillable = [
        'table_number', 'table_id', 'public_token', 'active_table_number', 'guest_count', 'opened_by',
        'closed_by', 'status', 'opened_at', 'expires_at', 'bill_requested_at',
        'requested_payment_method', 'subtotal', 'discount', 'vat_exemption', 'vat', 'total',
        'paid_at', 'paid_payment_method', 'paid_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat_exemption' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
            'opened_at' => 'datetime',
            'expires_at' => 'datetime',
            'bill_requested_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TableSessionPayment::class);
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }
}
