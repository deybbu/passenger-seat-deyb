<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    public function test_admin_home_url_resolves_to_dashboard(): void
    {
        $admin = new User(['name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'admin']);
        $this->assertEquals(route('admin.dashboard'), $admin->homeUrl());
    }

    public function test_admin_can_access_dashboard_and_modules(): void
    {
        $admin = new User(['id' => 1, 'name' => 'Administrator', 'email' => 'admin@example.com', 'role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Sales overview')
            ->assertSee('Low-stock alerts')
            ->assertSee('Recent orders');

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['range' => 30]))
            ->assertOk()
            ->assertViewHas('range', 30)
            ->assertSee('30 days');

        $this->actingAs($admin)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('Order management')
            ->assertSee('Orders');

        $this->actingAs($admin)
            ->get(route('admin.inventory'))
            ->assertOk()
            ->assertSee('Inventory management')
            ->assertSee('Ingredient inventory');

        $this->actingAs($admin)
            ->get(route('admin.pos'))
            ->assertOk()
            ->assertSee('POS & transactions is empty', false);

        $this->actingAs($admin)
            ->get(route('admin.menu-management'))
            ->assertOk()
            ->assertSee('Menu management')
            ->assertSee('Menu items');

        $this->actingAs($admin)
            ->get(route('admin.barcode'))
            ->assertOk()
            ->assertSee('Barcode receiving')
            ->assertSee('Receive stock by barcode')
            ->assertSee('Available ingredient codes');

        foreach ([
            'admin.recipes',
            'admin.waste',
            'admin.analytics',
            'admin.forecasting',
            'admin.reservations',
            'admin.chatbot',
            'admin.users',
            'admin.notifications',
            'admin.activity',
            'admin.settings',
        ] as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }

        $this->actingAs($admin)
            ->get(route('admin.orders', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('All')
            ->assertSee('Pending')
            ->assertSee('Preparing')
            ->assertSee('Ready')
            ->assertSee('Completed');
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

    public function test_admin_can_filter_and_advance_orders_through_statuses(): void
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
                ->get(route('admin.orders', ['status' => 'pending']))
                ->assertOk()
                ->assertSee($reference)
                ->assertSee('Mark Preparing');

            foreach (['preparing', 'ready', 'completed'] as $status) {
                $order = \App\Models\Order::query()->where('reference_number', $reference)->firstOrFail();
                $this->actingAs($admin)
                    ->patch(route('admin.orders.status', $order), ['status' => $status])
                    ->assertRedirect();
                $this->assertDatabaseHas('orders', ['reference_number' => $reference, 'status' => $status]);
            }

            $this->actingAs($admin)
                ->get(route('admin.orders', ['status' => 'pending']))
                ->assertOk()
                ->assertViewHas('orders', fn ($orders) => ! $orders->getCollection()->contains('reference_number', $reference));
            $this->actingAs($admin)
                ->get(route('admin.orders', ['status' => 'completed']))
                ->assertOk()
                ->assertViewHas('orders', fn ($orders) => $orders->getCollection()->contains('reference_number', $reference));
            $this->actingAs($admin)
                ->get(route('admin.dashboard', ['range' => 30]))
                ->assertOk()
                ->assertViewHas('salesChart', fn ($chart) => count($chart) === 30 && collect($chart)->sum('total') >= 120);
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
