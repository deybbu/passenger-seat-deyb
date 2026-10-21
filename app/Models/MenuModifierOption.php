<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuModifierOption extends Model
{
    protected $fillable = [
        'menu_modifier_group_id', 'name', 'price_delta', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['price_delta' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuModifierGroup::class, 'menu_modifier_group_id');
    }
}
