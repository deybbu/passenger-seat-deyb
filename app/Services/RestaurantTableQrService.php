<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\RestaurantTable;
use App\Models\SystemSetting;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RestaurantTableQrService
{
    public function isGloballyEnabled(): bool
    {
        $setting = SystemSetting::query()->find('qr_enabled');
        if ($setting === null || $setting->value === null) {
            return true;
        }

        return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function toggle(RestaurantTable $restaurantTable, User $user): RestaurantTable
    {
        return DB::transaction(function () use ($restaurantTable, $user): RestaurantTable {
            $table = RestaurantTable::query()->lockForUpdate()->findOrFail($restaurantTable->id);
            $enabled = ! $table->is_enabled;
            $table->update([
                'is_enabled' => $enabled,
                'qr_token' => $enabled && ! $table->qr_token
                    ? RestaurantTable::generateUniqueQrToken()
                    : $table->qr_token,
            ]);

            if ($enabled) {
                $hasOpenSession = TableSession::query()
                    ->whereNull('closed_at')
                    ->where(function ($query) use ($table): void {
                        $query->where('table_id', $table->id)
                            ->orWhere('active_table_number', $table->table_number);
                    })
                    ->exists();

                if (! $hasOpenSession) {
                    TableSession::query()->create([
                        'table_number' => $table->table_number,
                        'table_id' => $table->id,
                        'public_token' => Str::random(64),
                        'active_table_number' => $table->table_number,
                        'guest_count' => 1,
                        'opened_by' => $user->id,
                        'opened_at' => now(),
                    ]);
                }
            }

            ActivityLog::query()->create([
                'user_id' => $user->id,
                'action' => ($enabled ? 'Enabled' : 'Disabled').' QR ordering for '.$table->name,
                'module' => 'Table QR',
                'subject_type' => RestaurantTable::class,
                'subject_id' => $table->id,
                'metadata' => [
                    'table_number' => $table->table_number,
                    'table_name' => $table->name,
                    'is_enabled' => $enabled,
                ],
            ]);

            return $table->fresh();
        }, 3);
    }
}
