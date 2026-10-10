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

        Artisan::call('migrate', [
            '--database' => 'event_inquiries_test',
            '--path' => database_path('migrations/2026_10_12_000005_create_event_inquiries_table.php'),
            '--realpath' => true,
            '--force' => true,
        ]);
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
            ->assertRedirect(route('events'))
            ->assertSessionHas('event_inquiry');

        $this->assertDatabaseHas('event_inquiries', [
            'email' => 'guest@example.com',
            'status' => EventInquiry::STATUS_PENDING,
        ]);
        $this->assertTrue(EventInquiry::query()->whereDate('event_date', $eventDate)->exists());

        $inquiry = EventInquiry::query()->firstOrFail();

        $this->get(route('events'))
            ->assertOk()
            ->assertSee('Inquiry received')
            ->assertSee('Pending review')
            ->assertSee($inquiry->reference);
    }

    public function test_pending_event_date_is_unavailable_for_another_inquiry(): void
    {
        $eventDate = today()->addDay()->toDateString();
        EventInquiry::query()->create([
            ...$this->validInquiry($eventDate),
            'reference' => 'EV-TEST0001',
            'status' => EventInquiry::STATUS_PENDING,
        ]);

        $this->get(route('events'))
            ->assertViewHas('availability', fn (array $availability): bool => $availability[$eventDate] === 'pending');

        $this->from(route('events'))
            ->post(route('events.store'), $this->validInquiry($eventDate))
            ->assertRedirect(route('events'))
            ->assertSessionHasErrors('event_date');

        $this->assertDatabaseCount('event_inquiries', 1);
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
