<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventBlackoutDate;
use App\Models\EventDateHold;
use App\Models\EventInquiry;
use App\Models\EventProposal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventDateHoldService
{
    public function conflict(string $date, ?int $ignoreInquiryId = null): ?string
    {
        if (EventBlackoutDate::query()->whereDate('date', $date)->exists()) return 'This date is blocked by the restaurant.';
        $hold = EventDateHold::query()->whereDate('event_date', $date)->first();
        if ($hold && $hold->status === 'booked' && (int) $hold->event_inquiry_id !== $ignoreInquiryId) return 'This date is already booked.';
        if ($hold && $hold->status === 'held' && ($hold->expires_at === null || $hold->expires_at->isFuture()) && (int) $hold->event_inquiry_id !== $ignoreInquiryId) return 'This date is on hold for another customer.';
        $legacy = array_diff(EventInquiry::BLOCKING_STATUSES, ['proposal_sent_on_hold', 'proposal_accepted', 'deposit_submitted']);
        if (EventInquiry::query()->whereDate('event_date', $date)->whereIn('status', $legacy)
            ->when($ignoreInquiryId !== null, fn ($q) => $q->where('id', '<>', $ignoreInquiryId))->exists()) return 'This date is already booked or being reviewed.';
        return null;
    }

    public function acquire(EventInquiry $inquiry, EventProposal $proposal, Carbon $expiresAt): EventDateHold
    {
        return DB::transaction(function () use ($inquiry, $proposal, $expiresAt): EventDateHold {
            EventBlackoutDate::query()->whereDate('date', $inquiry->event_date)->lockForUpdate()->first();
            if ($reason = $this->conflict($inquiry->event_date->toDateString(), (int) $inquiry->id)) {
                throw ValidationException::withMessages(['event_date' => $reason]);
            }
            $hold = EventDateHold::query()->whereDate('event_date', $inquiry->event_date)->lockForUpdate()->first();
            $attributes = ['event_inquiry_id' => $inquiry->id, 'event_proposal_id' => $proposal->id, 'status' => 'held', 'expires_at' => $expiresAt, 'released_at' => null, 'release_reason' => null];
            if ($hold) {
                if ($hold->status === 'booked' || ($hold->status === 'held' && ($hold->expires_at === null || $hold->expires_at->isFuture()) && (int) $hold->event_inquiry_id !== (int) $inquiry->id)) {
                    throw ValidationException::withMessages(['event_date' => 'This date is no longer available.']);
                }
                $hold->update($attributes);
                return $hold->refresh();
            }
            EventDateHold::query()->insertOrIgnore([...$attributes, 'event_date' => $inquiry->event_date->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
            return EventDateHold::query()->whereDate('event_date', $inquiry->event_date)->lockForUpdate()->firstOrFail();
        });
    }

    public function release(EventInquiry $inquiry, string $reason): void
    {
        $hold = EventDateHold::query()->whereDate('event_date', $inquiry->event_date)->lockForUpdate()->first();
        if ($hold && (int) $hold->event_inquiry_id === (int) $inquiry->id && $hold->status === 'held') {
            $hold->update(['status' => 'released', 'released_at' => now(), 'release_reason' => $reason]);
        }
    }

    public function markBooked(EventInquiry $inquiry): EventDateHold
    {
        $hold = EventDateHold::query()->whereDate('event_date', $inquiry->event_date)->lockForUpdate()->first();
        if (! $hold || (int) $hold->event_inquiry_id !== (int) $inquiry->id || $hold->status !== 'held' || ($hold->expires_at !== null && $hold->expires_at->isPast())) {
            throw ValidationException::withMessages(['event_date' => 'The date hold has expired or is no longer active.']);
        }
        $hold->update(['status' => 'booked', 'expires_at' => null]);
        return $hold->refresh();
    }
}
