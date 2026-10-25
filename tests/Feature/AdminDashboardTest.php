<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\EventBlackoutDate;
use App\Models\EventInquiry;
use App\Models\EventPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_home_url_resolves_to_dashboard(): void
    {
        $admin = new User(['name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'admin']);
        $this->assertEquals(route('admin.dashboard'), $admin->homeUrl());
    }

    public function test_admin_can_access_dashboard_and_modules(): void
    {
        $admin = new User(['id' => 1, 'name' => 'Administrator', 'email' => 'admin@example.com', 'role' => 'admin']);

        foreach ([
            'admin.dashboard',
            'admin.orders',
            'admin.inventory',
            'admin.pos',
            'admin.menu-management',
            'admin.barcode',
            'admin.purchase-orders',
            'admin.lots',
            'admin.recipes',
            'admin.waste',
            'admin.analytics',
            'admin.menu-matrix',
            'admin.forecasting',
            'admin.reservations',
            'admin.chatbot',
            'admin.users',
            'admin.notifications',
            'admin.activity',
            'admin.settings',
        ] as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('id="view"', false);
        }

        $this->actingAs($admin)
            ->get(route('admin.slow-moving'))
            ->assertMovedPermanently()
            ->assertRedirect(route('admin.menu-matrix'));

        $this->actingAs($admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonStructure([
                'menu',
                'ingredients',
                'orders',
                'waste',
                'settings',
                'purchase_orders',
                'lots',
                'history',
                'tables',
                'inquiries',
                'packages',
                'blackouts',
                'faqs',
                'unanswered',
                'users',
                'roles',
                'rules',
                'alerts',
            ]);
    }

    public function test_menu_management_preview_uses_same_origin_image_urls(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Preview category',
            'slug' => 'preview-category',
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Preview dish',
            'slug' => 'preview-dish',
            'description' => 'Test image preview.',
            'image_path' => 'menu-items/preview-dish.jpg',
            'price' => 100,
            'is_active' => true,
            'is_available' => true,
        ]);
        $admin = new User(['id' => 1, 'name' => 'Administrator', 'email' => 'admin@example.com', 'role' => 'admin']);

        $this->actingAs($admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('menu.0.img', '/storage/menu-items/preview-dish.jpg');
    }

    public function test_admin_event_inquiries_and_blackouts_use_shared_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $eventDate = today()->addDay()->toDateString();
        $inquiry = EventInquiry::query()->create([
            'event_date' => $eventDate,
            'reference' => 'EV-SHARED01',
            'first_name' => 'Shared',
            'last_name' => 'Guest',
            'email' => 'shared@example.com',
            'mobile_number' => '09123456789',
            'event_type' => 'Birthday',
            'preferred_time' => '1:00 PM to 3:00 PM',
            'guest_count' => 12,
            'preferred_package' => 'Family-style menu around PHP 1,800 per guest',
            'budget' => 15000,
            'dietary_restrictions' => 'Vegetarian',
            'special_requests' => 'No balloons',
            'status' => EventInquiry::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('inquiries.0.n', 'Shared Guest')
            ->assertJsonPath('inquiries.0.em', 'shared@example.com')
            ->assertJsonPath('inquiries.0.ph', '09123456789')
            ->assertJsonPath('inquiries.0.time', '1:00 PM to 3:00 PM')
            ->assertJsonPath('inquiries.0.b', 15000)
            ->assertJsonPath('inquiries.0.pk', '')
            ->assertJsonPath('inquiries.0.preferredPackage', 'Family-style menu around PHP 1,800 per guest')
            ->assertJsonPath('inquiries.0.diet', 'Vegetarian')
            ->assertJsonPath('inquiries.0.requests', 'No balloons')
            ->assertJsonPath('blackouts', []);

        $this->patchJson(route('admin.api.reference.reservations.update', $inquiry), [
            'status' => 'approved',
        ])->assertOk();

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('availability', fn (array $availability): bool => $availability[$eventDate] === 'booked');

        $blackoutDate = today()->addDays(2)->toDateString();
        $this->postJson(route('admin.api.reference.reservations.blackout'), ['date' => $blackoutDate])
            ->assertOk()
            ->assertJsonPath('blocked', true);

        $this->actingAs($admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('blackouts.0', $blackoutDate);
    }

    public function test_customer_submission_is_available_in_the_admin_inquiry_feed(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->post(route('events.store'), [
            'event_date' => today()->addDay()->toDateString(),
            'first_name' => 'Landing',
            'last_name' => 'Guest',
            'email' => 'landing.guest@example.com',
            'mobile_number' => '09123456789',
            'event_type' => 'Birthday',
            'preferred_time_from' => '1:00 PM',
            'preferred_time_to' => '3:00 PM',
            'guest_count' => 18,
            'event_package_id' => EventPackage::query()
                ->where('min_guests', '<=', 18)
                ->where('max_guests', '>=', 18)
                ->value('id'),
            'preferred_package' => 'Custom family-style menu',
            'dietary_restrictions' => 'Vegetarian options',
            'special_requests' => 'Please include a child-friendly table.',
        ])->assertRedirect(route('home').'#reserve');

        $this->actingAs($admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('inquiries.0.n', 'Landing Guest')
            ->assertJsonPath('inquiries.0.em', 'landing.guest@example.com')
            ->assertJsonPath('inquiries.0.time', '1:00 PM to 3:00 PM')
            ->assertJsonPath('inquiries.0.preferredPackage', 'Custom family-style menu')
            ->assertJsonPath('inquiries.0.diet', 'Vegetarian options')
            ->assertJsonPath('inquiries.0.requests', 'Please include a child-friendly table.');
    }

    public function test_admin_can_mark_all_notifications_as_read(): void
    {
        $admin = new User(['id' => 1, 'name' => 'Administrator', 'email' => 'admin@example.com', 'role' => 'admin']);

        $this->actingAs($admin)
            ->postJson(route('admin.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('unread', 0);
    }

    public function test_recipe_relationship_uses_the_named_pivot_and_quantity(): void
    {
        $relation = (new MenuItem())->ingredients();

        $this->assertSame('ingredient_menu_item', $relation->getTable());
        $this->assertSame('menu_item_id', $relation->getForeignPivotKeyName());
        $this->assertSame('ingredient_id', $relation->getRelatedPivotKeyName());
        $this->assertContains('quantity', $relation->getPivotColumns());
    }

    public function test_admin_can_advance_order_statuses_and_reference_data_stays_current(): void
    {
        $admin = new User(['id' => 1, 'name' => 'Administrator', 'email' => 'admin@example.com', 'role' => 'admin']);
        $reference = 'PSR-TEST-'.Str::upper(Str::random(10));
        DB::beginTransaction();

        try {
            $orderAttributes = [
                'reference_number' => $reference,
                'type' => 'take_out',
                'order_type' => 'take_out',
                'subtotal' => 120,
                'total' => 120,
                'customer_name' => 'Feature test',
                'customer_phone' => null,
                'special_instructions' => null,
                'table_number' => null,
                'total_amount' => 120,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            DB::table('orders')->insert(array_intersect_key(
                $orderAttributes,
                array_flip(Schema::getColumnListing('orders')),
            ));

            $this->actingAs($admin)
                ->getJson(route('admin.api.reference.dashboard'))
                ->assertOk()
                ->assertJsonFragment(['no' => $reference, 'st' => 'Pending']);

            foreach (['preparing', 'ready', 'served'] as $status) {
                $order = \App\Models\Order::query()->where('reference_number', $reference)->firstOrFail();
                $this->actingAs($admin)
                    ->patch(route('admin.orders.status', $order), ['status' => $status])
                    ->assertRedirect();
                $this->assertDatabaseHas('orders', ['reference_number' => $reference, 'status' => $status]);
            }

            $this->actingAs($admin)
                ->getJson(route('admin.api.reference.dashboard'))
                ->assertOk()
                ->assertJsonFragment(['no' => $reference, 'st' => 'Served']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_cancelled_orders_are_included_in_admin_reference_data(): void
    {
        $admin = new User(['id' => 1, 'name' => 'Administrator', 'email' => 'admin@example.com', 'role' => 'admin']);
        $reference = 'PSR-CANCEL-'.Str::upper(Str::random(10));
        $customer = 'Searchable Customer '.Str::upper(Str::random(6));
        DB::beginTransaction();

        try {
            $orderAttributes = [
                'reference_number' => $reference,
                'type' => 'take_out',
                'order_type' => 'take_out',
                'subtotal' => 120,
                'total' => 120,
                'customer_name' => $customer,
                'customer_phone' => null,
                'special_instructions' => null,
                'table_number' => null,
                'total_amount' => 120,
                'status' => 'cancelled',
                'payment_status' => 'unpaid',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            DB::table('orders')->insert(array_intersect_key(
                $orderAttributes,
                array_flip(Schema::getColumnListing('orders')),
            ));

            $this->actingAs($admin)
                ->getJson(route('admin.api.reference.dashboard'))
                ->assertOk()
                ->assertJsonFragment([
                    'no' => $reference,
                    'st' => 'Cancelled',
                    'customer' => $customer,
                ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $kitchen = new User(['id' => 2, 'name' => 'Kitchen Staff', 'email' => 'kitchen@example.com', 'role' => 'kitchen']);

        $this->actingAs($kitchen)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_sign_out(): void
    {
        $admin = new User(['id' => 1, 'name' => 'Administrator', 'email' => 'admin@example.com', 'role' => 'admin']);

        $this->actingAs($admin)
            ->post('/staff/logout')
            ->assertRedirect('/staff/login');

        $this->assertGuest();
    }
}
