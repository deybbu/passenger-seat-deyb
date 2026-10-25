<?php

namespace App\Http\Controllers;

use App\Models\EventInquiry;
use App\Models\MenuItem;
use App\Models\EventPackage;
use App\Services\EventAvailabilityService;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function index(EventAvailabilityService $eventAvailability): View
    {
        $menuItems = MenuItem::query()
            ->with('category')
            ->withStockAvailability()
            ->where('is_active', true)
            ->whereNull('archived_at')
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

        $newItems = $menuItems
            ->filter(fn (MenuItem $item): bool => $item->collection_type === 'New')
            ->values();

        $availability = $eventAvailability->forPublicCalendar();

        return view('landing.home', compact(
            'menuItems',
            'menuCategories',
            'boodleItems',
            'newItems',
            'availability',
        ) + [
            'today' => today()->toDateString(),
            'eventTypes' => EventInquiry::EVENT_TYPES,
            'preferredTimes' => EventInquiry::PREFERRED_TIMES,
            'confirmation' => session('event_inquiry'),
            'eventPackages' => EventPackage::query()->where('is_active', true)->whereNotNull('min_guests')->whereNotNull('max_guests')->orderBy('min_guests')->get(),
        ]);
    }
}
