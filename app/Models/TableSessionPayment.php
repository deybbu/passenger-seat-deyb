<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TableSessionPayment extends Model
{
    protected $fillable = [
        'table_session_id',
        'type',
        'payment_method',
        'payment_reference',
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
        'performed_by',
        'reversal_of_id',
        'notes',
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
        ];
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }
}
