<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bill extends Model
{
    protected $fillable = [
        'table_session_id',
        'reference_number',
        'bill_number',
        'display_name',
        'split_method',
        'status',
        'subtotal',
        'discount',
        'vat_exemption',
        'vat',
        'total',
        'created_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'bill_number' => 'integer',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat_exemption' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
