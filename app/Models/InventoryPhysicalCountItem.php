<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryPhysicalCountItem extends Model
{
    protected $fillable = [
        'physical_count_id',
        'ingredient_id',
        'system_quantity',
        'counted_quantity',
        'variance_quantity',
        'variance_reason',
        'waste_stage',
        'adjusted',
        'inventory_log_id',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'variance_quantity' => 'decimal:3',
            'adjusted' => 'boolean',
        ];
    }

    public function physicalCount(): BelongsTo
    {
        return $this->belongsTo(InventoryPhysicalCount::class, 'physical_count_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function inventoryLog(): BelongsTo
    {
        return $this->belongsTo(InventoryLog::class);
    }
}
