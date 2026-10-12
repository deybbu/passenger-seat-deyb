<?php

namespace App\Http\Controllers;

use App\Models\EventInquiry;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $featuredItems = MenuItem::query()
            ->with('category')
            ->withStockAvailability()
            ->where('is_active', true)
            ->where('is_available', true)
            ->where('is_featured', true)
            ->orderBy('name')
            ->limit(3)
            ->get();

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

        return view('landing.home', compact('categories', 'featuredItems', 'availability'));
    }
}
