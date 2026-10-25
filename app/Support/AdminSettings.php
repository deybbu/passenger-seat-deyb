<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;

class AdminSettings
{
    public static function defaults(): array
    {
        return [
            'restaurant_name' => 'Passenger Seat',
            'tagline' => 'Restaurant Operations',
            'open_time' => '10:00',
            'close_time' => '22:00',
            'low_stock_alerts' => true,
            'order_alerts' => true,
            'recipe_target_percent' => 30,
        ];
    }

    public static function all(): array
    {
        $path = self::path();
        if (! File::exists($path)) {
            return self::defaults();
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? array_merge(self::defaults(), $decoded) : self::defaults();
    }

    public static function put(array $data): void
    {
        $dir = dirname(self::path());
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put(self::path(), json_encode(array_merge(self::all(), $data), JSON_PRETTY_PRINT));
    }

    private static function path(): string
    {
        return storage_path('app/admin-settings.json');
    }
}
