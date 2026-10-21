<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemBundle extends Model
{
    protected $fillable = ['menu_item_id', 'included_menu_item_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function includedItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'included_menu_item_id');
    }
}
