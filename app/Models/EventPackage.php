<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventPackage extends Model
{
    protected $fillable = ['name', 'description', 'price_per_guest', 'is_active'];

    protected function casts(): array
    {
        return [
            'price_per_guest' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class)
            ->withPivot('quantity_per_guest');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(EventInquiry::class);
    }
}
