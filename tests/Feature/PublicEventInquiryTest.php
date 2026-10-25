<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EventInquiry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\EventBlackoutDate;
use Tests\TestCase;

class PublicEventInquiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = ':memory:';

        config([
            'database.connections.event_inquiries_test' => $sqlite,
            'database.default' => 'event_inquiries_test',
        ]);
        DB::purge('event_inquiries_test');

        foreach ([
            '2026_10_10_000001_create_menu_categories_table.php',
            '2026_10_10_000002_create_menu_items_table.php',
            '2026_10_12_000005_create_event_inquiries_table.php',
        ] as $migration) {
            Artisan::call('migrate', [
                '--database' => 'event_inquiries_test',
                '--path' => database_path('migrations/'.$migration),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        Schema::create('event_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('min_guests')->nullable();
            $table->unsignedSmallInteger('max_guests')->nullable();
            $table->decimal('price_per_guest', 10, 2)->nullable();
            $table->decimal('fixed_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('event_inquiries', function (Blueprint $table): void {
            $table->foreignId('event_package_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('event_packages')->insert([
            [
                'id' => 1,
                'name' => 'Domestic Flight Platter',
                'description' => 'Filipino Comfort Grill.',
                'min_guests' => 10,
                'max_guests' => 15,
                'price_per_guest' => null,
                'fixed_price' => 7999,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Regional Flight Platter',
                'description' => 'Land & Sea Adventure.',
                'min_guests' => 16,
                'max_guests' => 20,
                'price_per_guest' => null,
                'fixed_price' => 11499,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'International Flight Platter',
                'description' => 'Premium Feast.',
                'min_guests' => 21,
                'max_guests' => 30,
                'price_per_guest' => null,
                'fixed_price' => 17999,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::create('event_blackout_dates', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_events_page_shows_the_availability_calendar_and_form(): void
    {
        $this->get(route('events'))
            ->assertOk()
            ->assertSee('Check Event Availability')
            ->assertSee('data-calendar-grid', false)
            ->assertSee('data-availability="[]"', false)
            ->assertSee('data-ticket-input="first_name"', false)
            ->assertSee('data-ticket-value="passenger"', false)
            ->assertSee('10:00 AM')
            ->assertSee('preferred_time_from', false)
            ->assertSee('preferred_time_to', false)
            ->assertSee('Domestic Flight Platter')
            ->assertSee('type="text" maxlength="255" value="" placeholder="Tell us what you have in mind"', false)
            ->assertSee('data-other-event-field', false)
            ->assertSee('Event Availability Calendar')
            ->assertSeeInOrder([
                '1. Event Availability Calendar',
                'Event Packages',
                'Domestic Flight Platter',
                'Regional Flight Platter',
                'International Flight Platter',
                '2. Contact Information',
            ])
            ->assertSee('Contact Information')
            ->assertSee('Event Requirements')
            ->assertSee('₱7,999.00 fixed tier price')
            ->assertSee('₱11,499.00 fixed tier price')
            ->assertSee('₱17,999.00 fixed tier price')
            ->assertSee('Submitting an inquiry does not automatically confirm your event booking.');
    }

    public function test_customer_inquiry_is_saved_pending_and_confirmation_is_shown(): void
    {
        $eventDate = today()->addDay()->toDateString();

        $this->post(route('events.store'), $this->validInquiry($eventDate))
            ->assertRedirect(route('home').'#reserve')
            ->assertSessionHas('event_inquiry');

        $this->assertDatabaseHas('event_inquiries', [
            'email' => 'guest@example.com',
            'status' => EventInquiry::STATUS_PENDING,
        ]);
        $this->assertTrue(EventInquiry::query()->whereDate('event_date', $eventDate)->exists());

        $inquiry = EventInquiry::query()->firstOrFail();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Inquiry Received')
            ->assertSee('Pending review')
            ->assertSee($inquiry->reference)
            ->assertSee('Your date is not confirmed yet');
    }

    public function test_customer_can_enter_a_custom_package_or_budget_request(): void
    {
        $inquiry = $this->validInquiry(today()->addDay()->toDateString());
        $inquiry['preferred_package'] = 'A family-style menu around PHP 1,800 per guest';

        $this->post(route('events.store'), $inquiry)
            ->assertRedirect(route('home').'#reserve');

        $this->assertDatabaseHas('event_inquiries', [
            'preferred_package' => 'A family-style menu around PHP 1,800 per guest',
        ]);
    }

    public function test_other_event_type_is_saved_and_shown_on_the_ticket(): void
    {
        $inquiry = $this->validInquiry(today()->addDay()->toDateString());
        $inquiry['event_type'] = 'Other';
        $inquiry['event_type_other'] = 'Graduation celebration';

        $this->post(route('events.store'), $inquiry)
            ->assertRedirect(route('home').'#reserve')
            ->assertSessionHas('event_inquiry.event_type', 'Graduation celebration');

        $this->assertDatabaseHas('event_inquiries', [
            'event_type' => 'Graduation celebration',
            'preferred_time' => '1:00 PM to 3:00 PM',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Graduation celebration')
            ->assertSee('1:00 PM to 3:00 PM')
            ->assertSee('12 PAX');
    }

    public function test_other_event_type_requires_a_custom_description(): void
    {
        $inquiry = $this->validInquiry(today()->addDay()->toDateString());
        $inquiry['event_type'] = 'Other';

        $this->from(route('events'))
            ->post(route('events.store'), $inquiry)
            ->assertRedirect(route('events'))
            ->assertSessionHasErrors('event_type_other');

        $this->assertDatabaseCount('event_inquiries', 0);
    }

    public function test_preferred_time_requires_an_end_time_after_the_start_time(): void
    {
        $inquiry = $this->validInquiry(today()->addDay()->toDateString());
        $inquiry['preferred_time_from'] = '3:00 PM';
        $inquiry['preferred_time_to'] = '1:00 PM';

        $this->from(route('events'))
            ->post(route('events.store'), $inquiry)
            ->assertRedirect(route('events'))
            ->assertSessionHasErrors('preferred_time_to');

        $this->assertDatabaseCount('event_inquiries', 0);
    }

    public function test_event_date_cutoff_uses_the_philippine_date_after_midnight(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));

        Carbon::setTestNow(
            Carbon::parse('2026-10-17 16:17:00', 'UTC')->setTimezone('Asia/Manila')
        );

        try {
            $this->get(route('events'))
                ->assertViewHas('today', '2026-10-18');
            $this->get(route('home'))
                ->assertViewHas('today', '2026-10-18');

            $this->from(route('events'))
                ->post(route('events.store'), $this->validInquiry('2026-10-17'))
                ->assertRedirect(route('events'))
                ->assertSessionHasErrors('event_date');

            $this->assertDatabaseCount('event_inquiries', 0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_homepage_shows_calendar_and_contact_form(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Check Event Availability')
            ->assertSee('data-event-calendar', false)
            ->assertSee('data-calendar-grid', false)
            ->assertSee('data-availability="[]"', false)
            ->assertSee('data-event-ticket', false)
            ->assertSee('data-ticket-input="first_name"', false)
            ->assertSee('1. Event Availability Calendar')
            ->assertSee('2. Contact Information')
            ->assertSee('3. Event Requirements')
            ->assertSee('event-inquiry-form')
            ->assertSee('name="event_date"', false)
            ->assertSee(route('events.store'), false)
            ->assertSee('Submitting an inquiry does not automatically confirm your event booking.');
    }

    public function test_pending_event_date_is_unavailable_for_another_inquiry(): void
    {
        $eventDate = today()->addDay()->toDateString();
        EventInquiry::query()->create([
            ...$this->validInquiry($eventDate),
            'reference' => 'EV-TEST0001',
            'status' => EventInquiry::STATUS_PENDING,
        ]);

        $this->get(route('home'))
            ->assertViewHas('availability', fn (array $availability): bool => $availability[$eventDate] === 'pending');

        $this->from(route('home'))
            ->post(route('events.store'), $this->validInquiry($eventDate))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('event_date');

        $this->assertDatabaseCount('event_inquiries', 1);
    }

    public function test_under_review_event_date_is_unavailable_for_another_inquiry(): void
    {
        $eventDate = today()->addDay()->toDateString();
        EventInquiry::query()->create([
            ...$this->validInquiry($eventDate),
            'reference' => 'EV-REVIEW01',
            'status' => 'under_review',
        ]);

        $this->get(route('home'))
            ->assertViewHas('availability', fn (array $availability): bool => $availability[$eventDate] === 'pending');

        $this->from(route('home'))
            ->post(route('events.store'), $this->validInquiry($eventDate))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('event_date');
    }

    public function test_admin_blackout_is_visible_on_public_calendars_and_rejects_submissions(): void
    {
        $eventDate = today()->addDay()->toDateString();
        EventBlackoutDate::query()->create(['date' => $eventDate, 'reason' => 'Private event']);

        $this->get(route('home'))
            ->assertViewHas('availability', fn (array $availability): bool => $availability[$eventDate] === 'blocked');
        $this->get(route('events'))
            ->assertViewHas('availability', fn (array $availability): bool => $availability[$eventDate] === 'blocked');

        $this->postJson(route('events.store'), $this->validInquiry($eventDate))
            ->assertUnprocessable()
            ->assertJsonPath('errors.event_date.0', 'That date is no longer available. Please choose another date.');

        $this->assertDatabaseCount('event_inquiries', 0);
    }

    public function test_pending_inquiry_is_included_in_admin_alerts(): void
    {
        EventInquiry::query()->create([
            ...$this->validInquiry(today()->addDay()->toDateString()),
            'reference' => 'EV-TEST0002',
            'status' => EventInquiry::STATUS_PENDING,
        ]);
        $request = request();
        $request->setLaravelSession(app('session.store'));

        $alerts = app(\App\Services\AdminAlertService::class)->all($request);

        $this->assertTrue($alerts->contains(
            fn (array $alert): bool => $alert['key'] === 'event-inquiry-1'
                && $alert['title'] === 'New event inquiry'
            && str_contains($alert['body'], 'guest@example.com')
                && str_contains($alert['body'], 'Test Guest')
                && $alert['unread'],
        ));
    }

    private function validInquiry(string $eventDate): array
    {
        return [
            'event_date' => $eventDate,
            'first_name' => 'Test',
            'last_name' => 'Guest',
            'email' => 'guest@example.com',
            'mobile_number' => '09123456789',
            'event_type' => 'Birthday',
            'preferred_time' => '1:00 PM to 3:00 PM',
            'preferred_time_from' => '1:00 PM',
            'preferred_time_to' => '3:00 PM',
            'guest_count' => 12,
            'event_package_id' => 1,
            'preferred_package' => 'Set menu',
            'dietary_restrictions' => null,
            'special_requests' => null,
        ];
    }
}
