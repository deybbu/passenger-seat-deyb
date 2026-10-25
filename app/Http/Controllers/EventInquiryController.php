<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EventInquiry;
use App\Models\EventBlackoutDate;
use App\Models\EventDateHold;
use App\Models\EventPackage;
use App\Models\SystemSetting;
use App\Services\EventAvailabilityService;
use App\Services\EventInquiryMailService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class EventInquiryController extends Controller
{
    public function index(EventAvailabilityService $eventAvailability): View
    {
        return view('landing.events', [
            'availability' => $eventAvailability->forPublicCalendar(),
            'today' => today()->toDateString(),
            'eventTypes' => EventInquiry::EVENT_TYPES,
            'preferredTimes' => EventInquiry::PREFERRED_TIMES,
            'confirmation' => session('event_inquiry'),
            'eventPackages' => EventPackage::query()->where('is_active', true)->whereNotNull('min_guests')->whereNotNull('max_guests')->orderBy('min_guests')->get(),
        ]);
    }

    public function deposit(EventInquiry $inquiry): View
    {
        abort_unless($inquiry->package !== null, 404);
        $settings = SystemSetting::query()
            ->whereIn('key', ['vat_percent', 'service_charge_percent'])
            ->get(['key', 'value'])->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->value]);
        $subtotal = round($inquiry->package->fixed_price !== null
            ? (float) $inquiry->package->fixed_price
            : (float) $inquiry->package->price_per_guest * $inquiry->guest_count, 2);
        $service = round($subtotal * (float) ($settings['service_charge_percent'] ?? 10) / 100, 2);
        $tax = round($subtotal * (float) ($settings['vat_percent'] ?? 12) / 100, 2);
        $total = round($subtotal + $service + $tax, 2);

        return view('landing.event-deposit', [
            'inquiry' => $inquiry->load('package.menuItems'),
            'subtotal' => $subtotal,
            'service' => $service,
            'tax' => $tax,
            'total' => $total,
            'deposit' => round($total * 0.5, 2),
        ]);
    }

    public function store(Request $request, EventInquiryMailService $inquiryMail): RedirectResponse|JsonResponse
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
            'event_type_other' => ['required_if:event_type,Other', 'nullable', 'string', 'max:120'],
            'preferred_time_from' => ['required', Rule::in(EventInquiry::PREFERRED_TIMES)],
            'preferred_time_to' => [
                'required',
                Rule::in(EventInquiry::PREFERRED_TIMES),
                function (string $attribute, string $value, \Closure $fail) use ($request): void {
                    $start = array_search($request->input('preferred_time_from'), EventInquiry::PREFERRED_TIMES, true);
                    $end = array_search($value, EventInquiry::PREFERRED_TIMES, true);

                    if ($start !== false && $end !== false && $end <= $start) {
                        $fail('End time must be later than start time.');
                    }
                },
            ],
            'guest_count' => ['required', 'integer', 'min:1', 'max:30'],
            'event_package_id' => [
                Rule::requiredIf((int) $request->input('guest_count', 0) >= 10),
                'nullable',
                'integer',
                Rule::exists('event_packages', 'id')->where('is_active', true),
            ],
            'preferred_package' => ['nullable', 'string', 'max:255'],
            'dietary_restrictions' => ['nullable', 'string', 'max:5000'],
            'special_requests' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($validated['event_package_id'])) {
            $selectedPackage = EventPackage::query()->find($validated['event_package_id']);
            if (! $selectedPackage || ! $selectedPackage->supportsGuestCount((int) $validated['guest_count'])) {
                $message = 'Choose a package that covers your number of guests.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message, 'errors' => ['event_package_id' => [$message]]], 422);
                }

                return back()->withErrors(['event_package_id' => $message])->withInput();
            }
        }

        if ($validated['event_type'] === 'Other') {
            $validated['event_type'] = trim($validated['event_type_other']);
        }
        unset($validated['event_type_other']);
        $validated['preferred_time'] = $validated['preferred_time_from'].' to '.$validated['preferred_time_to'];
        unset($validated['preferred_time_from'], $validated['preferred_time_to']);

        $inquiry = DB::transaction(function () use ($validated): ?EventInquiry {
            $dayAfterEvent = Carbon::parse($validated['event_date'])->addDay()->toDateString();
            $dateIsBlocked = EventBlackoutDate::query()
                ->whereDate('date', $validated['event_date'])
                ->lockForUpdate()
                ->exists()
                || EventInquiry::query()
                ->where('event_date', '>=', $validated['event_date'])
                ->where('event_date', '<', $dayAfterEvent)
                ->whereIn('status', array_diff(EventInquiry::BLOCKING_STATUSES, ['proposal_sent_on_hold', 'proposal_accepted', 'deposit_submitted']))
                ->lockForUpdate()
                ->first(['id']) !== null
                || EventDateHold::query()->whereDate('event_date', $validated['event_date'])
                    ->where(function ($query): void {
                        $query->where('status', 'booked')->orWhere(function ($active): void {
                            $active->where('status', 'held')->where('expires_at', '>', now());
                        });
                    })->lockForUpdate()->exists();

            if ($dateIsBlocked) {
                return null;
            }

            do {
                $reference = 'EV-'.Str::upper(Str::random(8));
            } while (EventInquiry::query()->where('reference', $reference)->exists());

            $attributes = [
                ...$validated,
                'reference' => $reference,
                'status' => EventInquiry::STATUS_PENDING,
            ];
            if (Schema::hasColumn('event_inquiries', 'event_package_id')) {
                $attributes['event_package_id'] = $validated['event_package_id'] ?? null;
            }

            return EventInquiry::query()->create($attributes);
        });

        if ($inquiry === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'That date is no longer available. Please choose another date.',
                    'errors' => ['event_date' => ['That date is no longer available. Please choose another date.']],
                ], 422);
            }

            return back()
                ->withErrors(['event_date' => 'That date is no longer available. Please choose another date.'])
                ->withInput();
        }

        $inquiryMailLog = $inquiryMail->sendAcknowledgement($inquiry);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'email_status' => $inquiryMailLog->status,
                'inquiry' => [
                    'reference' => $inquiry->reference,
                    'event_date' => $inquiry->event_date->toDateString(),
                    'event_type' => $inquiry->event_type,
                    'preferred_time' => $inquiry->preferred_time,
                    'guest_count' => $inquiry->guest_count,
                ],
            ]);
        }

        return redirect()
            ->to(route('home').'#reserve')
            ->with('event_inquiry', [
                'reference' => $inquiry->reference,
                'event_date' => $inquiry->event_date->toDateString(),
                'event_type' => $inquiry->event_type,
                'preferred_time' => $inquiry->preferred_time,
                'guest_count' => $inquiry->guest_count,
                'first_name' => $inquiry->first_name,
                'last_name' => $inquiry->last_name,
                'email' => $inquiry->email,
                'mobile_number' => $inquiry->mobile_number,
                'preferred_package' => $inquiry->preferred_package,
                'dietary_restrictions' => $inquiry->dietary_restrictions,
                'special_requests' => $inquiry->special_requests,
            ]);
    }

}
