<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventBlackoutDate;
use App\Models\EventInquiry;

class EventAvailabilityService
{
    public function forPublicCalendar(): array
    {
        $availability = EventInquiry::query()
            ->whereDate('event_date', '>=', today()->toDateString())
            ->whereIn('status', EventInquiry::BLOCKING_STATUSES)
            ->get(['event_date', 'status'])
            ->groupBy(fn (EventInquiry $inquiry): string => $inquiry->event_date->toDateString())
            ->map(fn ($inquiries): string => $inquiries->contains(
                fn (EventInquiry $inquiry): bool => in_array($inquiry->status, EventInquiry::BOOKED_STATUSES, true)
            ) ? 'booked' : 'pending')
            ->all();

        foreach (EventBlackoutDate::query()
            ->whereDate('date', '>=', today()->toDateString())
            ->pluck('date') as $date) {
            $availability[\Illuminate\Support\Carbon::parse($date)->toDateString()] = 'blocked';
        }

        return $availability;
    }
}
