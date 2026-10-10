<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    protected $fillable = ['name', 'current_stock', 'unit', 'min_threshold', 'manual_override'];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'min_threshold' => 'decimal:3',
            'manual_override' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function isLowStock(): bool
    {
        return !$this->manual_override && (float) $this->current_stock <= (float) $this->min_threshold;
    }
}
