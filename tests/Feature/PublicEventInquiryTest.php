<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EventInquiry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
    }

    public function test_events_page_shows_the_availability_calendar_and_form(): void
    {
        $this->get(route('events'))
            ->assertOk()
            ->assertSee('Check Event Availability')
            ->assertSee('Event Availability Calendar')
            ->assertSee('Contact Information')
            ->assertSee('Event Requirements')
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
            ->assertSee('Inquiry received')
            ->assertSee('Pending review')
            ->assertSee($inquiry->reference)
            ->assertSee('Your date is not confirmed yet');
    }

    public function test_homepage_shows_calendar_and_contact_form(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Check event availability')
            ->assertSee('1. Event availability calendar')
            ->assertSee('2. Contact information')
            ->assertSee('3. Event requirements')
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
            'preferred_time' => 'Lunch',
            'guest_count' => 12,
            'preferred_package' => 'Set menu',
            'dietary_restrictions' => null,
            'special_requests' => null,
        ];
    }
}
