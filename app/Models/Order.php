<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'reference_number', 'type', 'table_number', 'special_instructions',
        'total_amount', 'status', 'payment_status', 'payment_method', 'created_by', 'paid_at',
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
        return $this->belongsTo(User::class, 'created_by');
    }
}
