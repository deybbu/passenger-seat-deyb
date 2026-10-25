<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventPackage extends Model
{
    protected $fillable = ['name', 'description', 'min_guests', 'max_guests', 'price_per_guest', 'fixed_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'price_per_guest' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'min_guests' => 'integer',
            'max_guests' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function supportsGuestCount(int $guestCount): bool
    {
        return ($this->min_guests === null || $guestCount >= $this->min_guests)
            && ($this->max_guests === null || $guestCount <= $this->max_guests);
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
