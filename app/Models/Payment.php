<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Payment extends Model
{
    protected $fillable = [
        'idempotency_key',
        'payment_reference',
        'payment_method',
        'reference_number',
        'amount',
        'amount_tendered',
        'change_given',
        'subtotal',
        'discount',
        'vat_exemption',
        'vat',
        'total',
        'discount_type',
        'discount_rate',
        'discount_cardholder_name',
        'discount_id_number',
        'discount_tin',
        'cashier_id',
        'table_session_id',
        'bill_id',
        'paid_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_given' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat_exemption' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'payment_order')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }
}
