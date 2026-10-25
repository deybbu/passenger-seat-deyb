<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

class EventPaymentSettings
{
    public const DEFAULTS = [
        'deposit_percent' => '50', 'hold_duration_hours' => '72', 'bank_name' => '',
        'account_name' => '', 'account_number' => '', 'qr_image_path' => '',
        'contact_email' => '', 'contact_phone' => '',
        'deposit_policy' => 'The event is confirmed after the deposit has been received and verified.',
        'cancellation_policy' => '',
    ];

    public static function all(): array
    {
        $result = self::DEFAULTS;
        foreach (SystemSetting::query()->whereIn('key', array_keys(self::DEFAULTS))->get(['key', 'value']) as $setting) {
            $result[$setting->key] = (string) ($setting->value ?? '');
        }
        return $result;
    }

    public static function put(string $key, string $value): void
    {
        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'event_payments']);
    }

    public static function qrDataUri(?string $path = null): ?string
    {
        $path ??= self::all()['qr_image_path'];
        if ($path === '' || ! str_starts_with($path, 'event-payments/')) return null;
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) return null;
        $mime = $disk->mimeType($path);
        if (! in_array($mime, ['image/png', 'image/svg+xml'], true)) return null;
        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }
}
