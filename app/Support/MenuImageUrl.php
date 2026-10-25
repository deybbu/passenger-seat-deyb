<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class MenuImageUrl
{
    public static function forPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        if (config('filesystems.disks.public.driver') !== 'local') {
            return $url;
        }

        $urlPath = parse_url($url, PHP_URL_PATH);

        return is_string($urlPath) ? $urlPath : '/storage/'.ltrim($path, '/');
    }
}
