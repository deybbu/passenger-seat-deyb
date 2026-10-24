<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryIdempotencyKey extends Model
{
    protected $fillable = [
        'idempotency_key',
        'user_id',
        'operation',
        'request_hash',
        'status',
        'response_code',
        'response_payload',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
