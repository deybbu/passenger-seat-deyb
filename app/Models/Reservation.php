<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'guest_name', 'guest_phone', 'party_size', 'reserved_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'party_size' => 'integer',
        ];
    }
}
