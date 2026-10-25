<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventBlackoutDate;
use App\Models\EventInquiry;
use App\Models\EventDateHold;

class EventAvailabilityService
{
    public function forPublicCalendar(): array
    {
        $availability = EventInquiry::query()
            ->whereDate('event_date', '>=', today()->toDateString())
            ->whereIn('status', array_diff(EventInquiry::BLOCKING_STATUSES, ['proposal_sent_on_hold', 'proposal_accepted', 'deposit_submitted']))
            ->get(['event_date', 'status'])
            ->groupBy(fn (EventInquiry $inquiry): string => $inquiry->event_date->toDateString())
            ->map(fn ($inquiries): string => $inquiries->contains(
                fn (EventInquiry $inquiry): bool => in_array($inquiry->status, EventInquiry::BOOKED_STATUSES, true)
            ) ? 'booked' : 'pending')
            ->all();

        foreach (EventDateHold::query()->whereDate('event_date', '>=', today()->toDateString())
            ->where(function ($query): void {
                $query->where('status', 'booked')->orWhere(function ($active): void {
                    $active->where('status', 'held')->where('expires_at', '>', now());
                });
            })->get(['event_date', 'status']) as $hold) {
            $availability[$hold->event_date->toDateString()] = $hold->status === 'booked' ? 'booked' : 'pending';
        }

        foreach (EventBlackoutDate::query()
            ->whereDate('date', '>=', today()->toDateString())
            ->pluck('date') as $date) {
            $availability[\Illuminate\Support\Carbon::parse($date)->toDateString()] = 'blocked';
        }

        return $availability;
    }
}
