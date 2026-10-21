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

    protected $fillable = ['name', 'email', 'password', 'role', 'role_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function assignedRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function ordersCreated(): HasMany
    {
        return $this->hasMany(Order::class, 'cashier_id');
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function receivedInventoryLots(): HasMany
    {
        return $this->hasMany(InventoryStockLot::class, 'received_by');
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
