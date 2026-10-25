<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\TableSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Support\AddsOrderRecipe;

class AdminAnalyticsTest extends TestCase
{
    use AddsOrderRecipe;

    private User $admin;

    private User $cashier;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Manila',
            'database.default' => 'admin_analytics_test',
            'database.connections.admin_analytics_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('admin_analytics_test');
        DB::setDefaultConnection('admin_analytics_test');
        $this->artisan('migrate:fresh', ['--database' => 'admin_analytics_test'])->assertExitCode(0);
        $this->withoutVite();

        $this->admin = User::query()->create([
            'name' => 'Analytics Admin',
            'email' => 'analytics-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $cashierRole = Role::query()->where('key', 'cashier')->firstOrFail();
        $this->cashier = User::query()->create([
            'name' => 'Analytics Cashier',
            'email' => 'analytics-cashier@example.test',
            'password' => 'password',
            'role' => 'cashier',
            'role_id' => $cashierRole->id,
        ]);
        $category = MenuCategory::query()->create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
        ]);
        $this->menuItem = MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Analytics meal',
            'slug' => 'analytics-meal',
            'price' => 100,
            'is_active' => true,
            'is_available' => true,
        ]);
        $this->addOrderRecipe($this->menuItem, 'Analytics stock');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_pos_and_table_qr_cashier_payments_are_reported_by_method_and_reconcile(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00', 'Asia/Manila'));

        foreach (['cash', 'gcash', 'card'] as $method) {
            $payload = [
                'type' => 'take_out',
                'table_number' => '',
                'customer_name' => 'POS '.$method,
                'items' => [$this->menuItem->id => 1],
                'payment_method' => $method,
            ];
            if ($method === 'cash') {
                $payload['amount_tendered'] = 100;
            }

            $this->actingAs($this->admin)
                ->postJson(route('admin.api.reference.register.place'), $payload)
                ->assertOk();
        }

        $table = RestaurantTable::query()->create([
            'table_number' => '18',
            'name' => 'Table 18',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);
        $session = TableSession::query()->create([
            'table_number' => '18',
            'table_id' => $table->id,
            'public_token' => Str::random(64),
            'active_table_number' => '18',
            'status' => 'bill_requested',
            'opened_at' => Carbon::now('Asia/Manila'),
            'bill_requested_at' => Carbon::now('Asia/Manila'),
            'requested_payment_method' => 'cash',
            'subtotal' => 100,
            'total' => 100,
        ]);
        $tableOrder = $this->createOrder('unpaid', null, 100, Carbon::now('Asia/Manila'), [
            'table_id' => $table->id,
            'table_number' => '18',
            'table_session_id' => $session->id,
            'batch_number' => 1,
            'source' => 'table_qr',
        ]);
        $tableOrder->items()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => $this->menuItem->name,
            'category_name_snapshot' => 'Mains',
            'quantity' => 1,
            'price' => 100,
            'line_total' => 100,
        ]);

        $this->actingAs($this->cashier)
            ->post(route('staff.pos.table-session-paid', $session), [
                'payment_method' => 'gcash',
                'reference_number' => 'GC-SESSION-18',
            ])
            ->assertRedirect(route('staff.pos.index'));
        $this->assertSame('gcash', $tableOrder->fresh()->payment_method);
        $this->assertDatabaseHas('payments', [
            'table_session_id' => $session->id,
            'payment_method' => 'gcash',
        ]);

        $cancelled = $this->createOrder('paid', 'cash', 500, Carbon::now('Asia/Manila'), [
            'status' => 'cancelled',
        ]);
        $cancelled->items()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => $this->menuItem->name,
            'category_name_snapshot' => 'Mains',
            'quantity' => 5,
            'price' => 100,
            'line_total' => 500,
        ]);
        $unpaid = $this->createOrder('unpaid', null, 700, Carbon::now('Asia/Manila'));
        $unpaid->items()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => $this->menuItem->name,
            'category_name_snapshot' => 'Mains',
            'quantity' => 7,
            'price' => 100,
            'line_total' => 700,
        ]);
        $this->createOrder('paid', null, 50, Carbon::now('Asia/Manila'));

        $report = $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.dashboard', ['range' => 'DAILY']))
            ->assertOk()
            ->json('analytics');

        $methods = collect($report['paymentMethods'])->keyBy('method');
        $this->assertSame(1, $methods['Cash']['orders']);
        $this->assertSame(2, $methods['GCash']['orders']);
        $this->assertSame(1, $methods['Card']['orders']);
        $this->assertSame(1, $methods['Not recorded']['orders']);
        $this->assertEquals(100, $methods['Cash']['sales']);
        $this->assertEquals(200, $methods['GCash']['sales']);
        $this->assertEquals(100, $methods['Card']['sales']);
        $this->assertEquals(50, $methods['Not recorded']['sales']);
        $this->assertSame(5, $report['totalOrders']);
        $this->assertEquals(450, $report['totalSales']);
        $this->assertEquals($report['totalSales'], collect($report['paymentMethods'])->sum('sales'));
        $this->assertEquals($report['totalSales'], collect($report['salesByCategory'])->sum('sales'));
        $this->assertEquals($report['totalSales'], collect($report['topProducts'])->sum('revenue'));
    }

    public function test_analytics_range_filters_paid_sales_using_manila_paid_at_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00', 'Asia/Manila'));
        $this->createOrder('paid', 'cash', 100, Carbon::parse('2026-10-20 23:30:00', 'Asia/Manila'));
        $this->createOrder('paid', 'gcash', 200, Carbon::parse('2026-10-06 12:00:00', 'Asia/Manila'));
        $this->createOrder('paid', 'card', 300, Carbon::parse('2026-08-22 12:00:00', 'Asia/Manila'));

        $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.dashboard', ['range' => '7D']))
            ->assertOk()
            ->assertJsonPath('analytics.totalOrders', 1)
            ->assertJsonPath('analytics.totalSales', 100)
            ->assertJsonPath('analytics.salesChart.5.label', '10-20')
            ->assertJsonPath('analytics.salesChart.5.total', 100);

        $this->getJson(route('admin.api.reference.dashboard', ['range' => '30D']))
            ->assertOk()
            ->assertJsonPath('analytics.totalOrders', 2)
            ->assertJsonPath('analytics.totalSales', 300);

        $this->getJson(route('admin.api.reference.dashboard', ['range' => 'YEAR']))
            ->assertOk()
            ->assertJsonPath('analytics.totalOrders', 3)
            ->assertJsonPath('analytics.totalSales', 600)
            ->assertJsonPath('analytics.salesChart.9.total', 300);
    }

    public function test_custom_analytics_period_and_previous_period_comparison_use_paid_at_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00', 'Asia/Manila'));
        $this->createOrder('paid', 'cash', 100, Carbon::parse('2026-10-17 10:00:00', 'Asia/Manila'));
        $this->createOrder('paid', 'gcash', 50, Carbon::parse('2026-10-18 10:00:00', 'Asia/Manila'));
        $this->createOrder('paid', 'card', 200, Carbon::parse('2026-10-19 23:30:00', 'Asia/Manila'));
        $this->createOrder('paid', null, 150, Carbon::parse('2026-10-20 10:00:00', 'Asia/Manila'));
        $this->createOrder('unpaid', 'cash', 999, Carbon::parse('2026-10-20 11:00:00', 'Asia/Manila'));

        $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.analytics', [
                'from' => '2026-10-19',
                'to' => '2026-10-20',
                'compare' => 'previous',
            ]))
            ->assertOk()
            ->assertJsonPath('analytics.range', 'CUSTOM')
            ->assertJsonPath('analytics.totalOrders', 2)
            ->assertJsonPath('analytics.totalSales', 350)
            ->assertJsonPath('analytics.salesChart.0.label', '10-19')
            ->assertJsonPath('analytics.salesChart.1.total', 150)
            ->assertJsonPath('comparison.totalOrders', 2)
            ->assertJsonPath('comparison.totalSales', 150);

        $this->getJson(route('admin.api.reference.analytics', [
            'from' => '2026-10-20',
            'to' => '2026-10-19',
        ]))->assertUnprocessable();
    }

    private function createOrder(string $paymentStatus, ?string $paymentMethod, float $amount, Carbon $paidAt, array $extra = []): Order
    {
        return Order::query()->create([
            'reference_number' => 'AN-'.Str::upper(Str::random(12)),
            'type' => 'take_out',
            'order_type' => 'take_out',
            'customer_name' => 'Analytics test',
            'subtotal' => $amount,
            'total' => $amount,
            'total_amount' => $amount,
            'status' => 'pending',
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'paid_at' => $paymentStatus === 'paid' ? $paidAt : null,
            'cashier_id' => $this->cashier->id,
            ...$extra,
        ]);
    }
}
