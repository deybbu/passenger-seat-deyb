<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIngredientReservation extends Model
{
    protected $fillable = [
        'order_id', 'ingredient_id', 'ingredient_name', 'unit', 'quantity', 'status',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
