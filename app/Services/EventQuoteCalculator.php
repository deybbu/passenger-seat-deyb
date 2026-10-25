<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventInquiry;
use App\Models\SystemSetting;
use App\Support\EventPaymentSettings;

class EventQuoteCalculator
{
    public function calculate(EventInquiry $inquiry): array
    {
        $inquiry->loadMissing('package.menuItems');
        $package = $inquiry->package;
        $fixed = $package?->fixed_price;
        $perGuest = $package?->price_per_guest;
        $available = $package !== null && ($fixed !== null || $perGuest !== null);
        $subtotal = ! $available ? 0.0 : round($fixed !== null ? (float) $fixed : (float) $perGuest * $inquiry->guest_count, 2);
        $servicePercent = $this->setting('service_charge_percent', 10);
        $taxPercent = $this->setting('vat_percent', 12);
        $depositPercent = (float) EventPaymentSettings::all()['deposit_percent'];
        $service = round($subtotal * $servicePercent / 100, 2);
        $tax = round($subtotal * $taxPercent / 100, 2);
        $total = round($subtotal + $service + $tax, 2);

        return [
            'available' => $available, 'mode' => $fixed !== null ? 'fixed' : 'per_guest',
            'unit' => $fixed !== null ? (float) $fixed : ($perGuest === null ? null : (float) $perGuest),
            'subtotal' => $subtotal, 'servicePercent' => $servicePercent, 'service' => $service,
            'taxPercent' => $taxPercent, 'tax' => $tax, 'taxBasis' => 'subtotal_only', 'total' => $total,
            'depositPercent' => $depositPercent, 'deposit' => round($total * $depositPercent / 100, 2),
            'packageItems' => $package?->menuItems->pluck('name')->values()->all() ?? [],
            'packageDescription' => $package?->description ?? '',
        ];
    }

    private function setting(string $key, float $default): float
    {
        $value = SystemSetting::query()->whereKey($key)->first(['value'])?->value;
        return $value === null ? $default : (float) $value;
    }
}
