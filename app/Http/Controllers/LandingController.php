<?php

namespace App\Http\Controllers;

use App\Models\EventInquiry;
use App\Models\MenuItem;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $menuItems = MenuItem::query()
            ->with('category')
            ->withStockAvailability()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $menuCategories = $menuItems
            ->map(fn (MenuItem $item): ?string => $item->category?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($menuItems->contains(fn (MenuItem $item): bool => $item->category === null)) {
            $menuCategories->push('Uncategorized');
        }

        $boodleItems = $menuItems
            ->filter(fn (MenuItem $item): bool => str_contains(
                mb_strtolower($item->category?->name ?? ''),
                'boodle',
            ))
            ->values();

        $featuredItems = $menuItems
            ->filter(fn (MenuItem $item): bool => $item->is_featured && $item->isOrderable())
            ->take(3)
            ->values();

        $blockedInquiries = EventInquiry::query()
            ->whereDate('event_date', '>=', today()->toDateString())
            ->whereIn('status', EventInquiry::BLOCKING_STATUSES)
            ->get(['event_date', 'status']);

        $availability = $blockedInquiries
            ->groupBy(fn (EventInquiry $inquiry): string => $inquiry->event_date->toDateString())
            ->map(fn ($inquiries): string => $inquiries->contains(
                fn (EventInquiry $inquiry): bool => in_array($inquiry->status, EventInquiry::BOOKED_STATUSES, true)
            ) ? 'booked' : 'pending')
            ->all();

        return view('landing.home', compact(
            'menuItems',
            'menuCategories',
            'boodleItems',
            'featuredItems',
            'availability',
        ) + [
            'today' => today()->toDateString(),
            'eventTypes' => EventInquiry::EVENT_TYPES,
            'preferredTimes' => EventInquiry::PREFERRED_TIMES,
            'packageOptions' => EventInquiry::PACKAGE_OPTIONS,
            'confirmation' => session('event_inquiry'),
        ]);
    }
}
