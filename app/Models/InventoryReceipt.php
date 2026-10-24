<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryReceipt extends Model
{
    protected $fillable = [
        'package_id',
        'idempotency_key',
        'status',
        'scanned_by',
        'scanned_at',
        'received_by',
        'received_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(InventoryPackage::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function stockLot(): HasOne
    {
        return $this->hasOne(InventoryStockLot::class, 'receipt_id');
    }
}
