<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ingredient;
use App\Models\EventInquiry;
use App\Models\Order;
use App\Support\AdminSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AdminAlertService
{
    public const SESSION_KEY = 'admin_read_alerts';

    public function all(Request $request): Collection
    {
        $read = collect($request->session()->get(self::SESSION_KEY, []));
        $alerts = collect();
        $settings = AdminSettings::all();

        if ($settings['low_stock_alerts'] && Schema::hasTable('ingredients')) {
            $lowStock = Ingredient::query()
                ->withUsableStock()
                ->get()
                ->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())
                ->sortBy(fn (Ingredient $ingredient): float => $ingredient->usableStock())
                ->values();

            foreach ($lowStock as $ingredient) {
                $key = 'stock-'.$ingredient->id;
                $alerts->push([
                    'key' => $key,
                    'type' => 'stock',
                    'title' => 'Low stock',
                    'body' => $ingredient->name.' has '.$this->qty($ingredient->usableStock()).' usable '.$ingredient->unit.' (par '.$this->qty($ingredient->min_threshold).' '.$ingredient->unit.').',
                    'href' => route('admin.inventory'),
                    'time' => optional($ingredient->updated_at)?->diffForHumans() ?? 'Just now',
                    'unread' => ! $read->contains($key),
                ]);
            }
        }

        if ($settings['order_alerts'] && Schema::hasTable('orders')) {
            $orders = Order::query()
                ->whereIn('status', ['pending', 'preparing'])
                ->latest()
                ->limit(12)
                ->get();

            foreach ($orders as $order) {
                $key = 'order-'.$order->id;
                $alerts->push([
                    'key' => $key,
                    'type' => 'order',
                    'title' => $order->status === 'pending' ? 'New order' : 'Order in kitchen',
                    'body' => $order->reference_number.' is '.ucfirst($order->status).'.',
                    'href' => route('admin.orders', ['status' => $order->status]),
                    'time' => optional($order->created_at)?->diffForHumans() ?? 'Just now',
                    'unread' => ! $read->contains($key),
                ]);
            }
        }

        if (Schema::hasTable('event_inquiries')) {
            $inquiries = EventInquiry::query()
                ->where('status', EventInquiry::STATUS_PENDING)
                ->latest()
                ->limit(12)
                ->get();

            foreach ($inquiries as $inquiry) {
                $key = 'event-inquiry-'.$inquiry->id;
                $alerts->push([
                    'key' => $key,
                    'type' => 'event',
                    'title' => 'New event inquiry',
                    'body' => $inquiry->reference.' from '.$inquiry->first_name.' '.$inquiry->last_name
                        .' requests '.$inquiry->event_type.' for '.$inquiry->event_date->toFormattedDateString()
                        .' ('.$inquiry->guest_count.' guests). Contact: '.$inquiry->email.', '.$inquiry->mobile_number.'.',
                    'href' => route('admin.notifications'),
                    'time' => optional($inquiry->created_at)?->diffForHumans() ?? 'Just now',
                    'unread' => ! $read->contains($key),
                ]);
            }
        }

        return $alerts->values();
    }

    public function markRead(Request $request, string $key): void
    {
        $read = collect($request->session()->get(self::SESSION_KEY, []));
        $request->session()->put(self::SESSION_KEY, $read->push($key)->unique()->values()->all());
    }

    public function markAllRead(Request $request): void
    {
        $keys = $this->all($request)->pluck('key')->all();
        $request->session()->put(self::SESSION_KEY, $keys);
    }

    private function qty(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.') ?: '0';
    }
}
