<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EventInquiry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventInquiryController extends Controller
{
    public function index(): View
    {
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

        return view('landing.events', [
            'availability' => $availability,
            'today' => today()->toDateString(),
            'eventTypes' => EventInquiry::EVENT_TYPES,
            'preferredTimes' => EventInquiry::PREFERRED_TIMES,
            'packageOptions' => EventInquiry::PACKAGE_OPTIONS,
            'confirmation' => session('event_inquiry'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'mobile_number' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, string $value, \Closure $fail): void {
                    $digits = preg_replace('/\D+/', '', $value) ?? '';

                    if (strlen($digits) < 10) {
                        $fail('Enter a mobile number with at least 10 digits.');
                    }
                },
            ],
            'event_type' => ['required', Rule::in(EventInquiry::EVENT_TYPES)],
            'preferred_time' => ['required', Rule::in(EventInquiry::PREFERRED_TIMES)],
            'guest_count' => ['required', 'integer', 'min:1'],
            'preferred_package' => ['nullable', Rule::in(EventInquiry::PACKAGE_OPTIONS)],
            'dietary_restrictions' => ['nullable', 'string', 'max:5000'],
            'special_requests' => ['nullable', 'string', 'max:5000'],
        ]);

        $inquiry = DB::transaction(function () use ($validated): ?EventInquiry {
            $dayAfterEvent = Carbon::parse($validated['event_date'])->addDay()->toDateString();
            $dateIsBlocked = EventInquiry::query()
                ->where('event_date', '>=', $validated['event_date'])
                ->where('event_date', '<', $dayAfterEvent)
                ->whereIn('status', EventInquiry::BLOCKING_STATUSES)
                ->lockForUpdate()
                ->first(['id']) !== null;

            if ($dateIsBlocked) {
                return null;
            }

            do {
                $reference = 'EV-'.Str::upper(Str::random(8));
            } while (EventInquiry::query()->where('reference', $reference)->exists());

            return EventInquiry::query()->create([
                ...$validated,
                'reference' => $reference,
                'status' => EventInquiry::STATUS_PENDING,
            ]);
        });

        if ($inquiry === null) {
            return back()
                ->withErrors(['event_date' => 'That date is no longer available. Please choose another date.'])
                ->withInput();
        }

        return redirect()
            ->route('events')
            ->with('event_inquiry', [
                'reference' => $inquiry->reference,
                'event_date' => $inquiry->event_date->toDateString(),
                'event_type' => $inquiry->event_type,
                'guest_count' => $inquiry->guest_count,
            ]);
    }
}
