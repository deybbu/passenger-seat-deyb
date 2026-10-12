<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['admin', 'kitchen', 'cashier', 'inventory'];

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function ordersCreated(): HasMany
    {
        return $this->hasMany(Order::class, 'cashier_id');
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function tableSessionsOpened(): HasMany
    {
        return $this->hasMany(TableSession::class, 'opened_by');
    }

    public function tableSessionsClosed(): HasMany
    {
        return $this->hasMany(TableSession::class, 'closed_by');
    }

    public function orderStatusChanges(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'changed_by');
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function homeUrl(): string
    {
        return match ($this->role) {
            'admin' => route('admin.dashboard'),
            'kitchen' => route('staff.kitchen.index'),
            'cashier' => route('staff.pos.index'),
            'inventory' => route('inventory.index'),
            default => throw new \LogicException('User does not have a valid staff role.'),
        };
    }
}
