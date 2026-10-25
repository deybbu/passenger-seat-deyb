<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RestaurantTable extends Model
{
    protected $fillable = ['table_number', 'name', 'is_enabled', 'qr_token'];

    protected static function booted(): void
    {
        static::creating(function (RestaurantTable $table): void {
            if ($table->qr_token === null || $table->qr_token === '') {
                $table->qr_token = static::generateUniqueQrToken();
            }
        });
    }

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public static function generateUniqueQrToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::query()->where('qr_token', $token)->exists());

        return $token;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'table_id');
    }
}
