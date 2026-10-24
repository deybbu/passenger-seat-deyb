<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\TableSession;
use App\Models\User;
use App\Support\TableQrUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Support\AddsOrderRecipe;

class PosPaymentIntegrationTest extends TestCase
{
    use AddsOrderRecipe;

    private User $cashier;

    private User $secondCashier;

    private User $admin;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Manila',
            'database.default' => 'pos_payment_test',
            'database.connections.pos_payment_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('pos_payment_test');
        DB::setDefaultConnection('pos_payment_test');
        $this->artisan('migrate:fresh', ['--database' => 'pos_payment_test'])->assertExitCode(0);
        $this->withoutVite();

        $cashierRole = Role::query()->where('key', 'cashier')->firstOrFail();
        $this->cashier = $this->makeUser('POS Cashier', 'pos-cashier@example.test', 'cashier', $cashierRole->id);
        $this->secondCashier = $this->makeUser('Second Cashier', 'pos-cashier-2@example.test', 'cashier', $cashierRole->id);
        $this->admin = $this->makeUser('POS Admin', 'pos-admin@example.test', 'admin');

        $category = MenuCategory::query()->create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
        ]);
        $this->menuItem = MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Integration meal',
            'slug' => 'integration-meal',
            'price' => 100,
            'is_active' => true,
            'is_available' => true,
        ]);
        $this->addOrderRecipe($this->menuItem, 'POS stock');
    }

    public function test_register_rejects_identification_discount_without_cardholder_id(): void
    {
        $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.orders.store'), $this->registerPayload([
                'discount_type' => 'SC',
                'discount_cardholder_name' => 'Senior Customer',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_id_number']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_register_rejects_cash_below_total_and_gcash_without_reference(): void
    {
        $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.orders.store'), $this->registerPayload([
                'amount_tendered' => 99,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_tendered']);

        $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.orders.store'), $this->registerPayload([
                'payment_method' => 'gcash',
                'amount_tendered' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference_number']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_register_retry_with_same_idempotency_key_records_order_and_payment_once(): void
    {
        $payload = $this->registerPayload();
        $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.orders.store'), $payload)
            ->assertCreated();

        $retry = $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.orders.store'), $payload)
            ->assertOk();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, $retry->json('id'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'Register order payment recorded']);
    }

    public function test_item_split_that_assigns_outside_the_bill_count_is_rejected(): void
    {
        [$table, $session, $order] = $this->createGuestTableOrder();

        $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.table-session-bills.store', $session), [
                'split_method' => 'item',
                'bill_count' => 2,
                'assignments' => [[
                    'order_item_id' => $order->items()->firstOrFail()->id,
                    'bill_number' => 3,
                ]],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('bills', 0);
        $this->assertSame('open', $session->fresh()->status);
    }

    public function test_guest_qr_batch_and_requested_payment_method_reach_cashier_data(): void
    {
        [$table, $session] = $this->createGuestTableOrder();
        $this->postJson(route('customer.order.bill-request', ['tableToken' => $table->qr_token]), [
            'payment_method' => 'gcash',
        ])->assertOk();

        $data = $this->actingAs($this->cashier)
            ->getJson(route('staff.pos.data'))
            ->assertOk()
            ->assertJsonPath('sessions.0.items.0.src', 'QR')
            ->assertJsonPath('sessions.0.requestedPayMethod', 'GCash');

        $requests = $this->getJson(route('staff.pos.bill-requests'))
            ->assertOk()
            ->assertJsonPath('requests.0.payment_method', 'gcash')
            ->assertJsonPath('requests.0.table_number', '12');

        $this->assertNotEmpty($data->json('sessions'));
        $this->assertNotEmpty($requests->json('requests'));
        $this->assertStringContainsString('Guest QR', file_get_contents(public_path('js/pos.js')));
        $this->assertSame($session->id, (int) $data->json('sessions.0.id'));
    }

    public function test_admin_and_cashier_table_qr_controls_share_tables_tokens_and_real_qr_images(): void
    {
        $table = RestaurantTable::query()->create([
            'table_number' => '21',
            'name' => 'Table 21',
            'is_enabled' => false,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.api.reference.table-qr.toggle', $table))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('qr_url', TableQrUrl::forToken($table->fresh()->qr_token));

        $cashierData = $this->actingAs($this->cashier)
            ->getJson(route('staff.pos.data'))
            ->assertOk()
            ->assertJsonPath('tables.0.table_number', '21')
            ->assertJsonPath('tables.0.en', true)
            ->assertJsonPath('tables.0.u', TableQrUrl::forToken($table->qr_token))
            ->assertJsonPath('tables.0.img', route('staff.pos.table-qr.image', $table, false));

        $this->get(route('staff.pos.table-qr.image', $table))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertSee('<svg', false);

        $this->postJson(route('staff.pos.table-qr.toggle', ['restaurantTable' => $table->table_number]))
            ->assertOk()
            ->assertJsonPath('en', false);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.api.reference.table-qr.toggle', $table))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('table_number', '21');

        $this->assertSame($table->qr_token, $cashierData->json('tables.0.t'));
        $this->assertTrue($table->fresh()->is_enabled);
    }

    public function test_cashier_table_qrs_load_even_when_billing_migrations_are_still_pending(): void
    {
        $table = RestaurantTable::query()->create([
            'table_number' => '22',
            'name' => 'Table 22',
            'is_enabled' => true,
        ]);
        TableSession::query()->create([
            'table_number' => '22',
            'table_id' => $table->id,
            'public_token' => (string) Str::random(64),
            'active_table_number' => '22',
            'opened_by' => $this->cashier->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        Schema::disableForeignKeyConstraints();
        foreach (['payment_order', 'payments', 'bill_items', 'bills'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->cashier)
            ->getJson(route('staff.pos.data'))
            ->assertOk()
            ->assertJsonPath('tables.0.table_number', '22')
            ->assertJsonPath('tables.0.en', true)
            ->assertJsonPath('tables.0.t', $table->qr_token)
            ->assertJsonPath('sessions.0.id', $table->tableSessions()->value('id'))
            ->assertJsonPath('sessions.0.bills', []);
    }

    public function test_two_cashiers_cannot_pay_the_same_bill_twice_and_last_bill_closes_table(): void
    {
        [$table, $session] = $this->createGuestTableOrder();
        $split = $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.table-session-bills.store', $session), [
                'split_method' => 'even',
                'bill_count' => 2,
            ])
            ->assertOk();
        $bills = $split->json('bills');

        $firstPay = [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'amount_tendered' => 50,
        ];
        $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.bills.pay', $bills[0]['id']), $firstPay)
            ->assertOk()
            ->assertJsonPath('closed', false);

        $this->actingAs($this->secondCashier)
            ->postJson(route('staff.pos.bills.pay', $bills[0]['id']), [
                ...$firstPay,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertStatus(409);

        $this->actingAs($this->secondCashier)
            ->postJson(route('staff.pos.bills.pay', $bills[1]['id']), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'gcash',
                'reference_number' => 'GC-SECOND-BILL',
            ])
            ->assertOk()
            ->assertJsonPath('closed', true);

        $this->assertDatabaseCount('payments', 2);
        $this->assertSame('closed', $session->fresh()->status);
        $this->assertNull($session->fresh()->active_table_number);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Bill payment recorded']);
        $this->getJson(route('customer.order.session', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertJsonPath('session', null);
    }

    public function test_dashboard_analytics_reports_cashier_recorded_payment_methods(): void
    {
        $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.orders.store'), $this->registerPayload())
            ->assertCreated();
        $this->actingAs($this->cashier)
            ->postJson(route('staff.pos.orders.store'), $this->registerPayload([
                'payment_method' => 'gcash',
                'amount_tendered' => null,
                'reference_number' => 'GC-ANALYTICS',
            ]))
            ->assertCreated();

        $analytics = $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.dashboard', ['range' => 'DAILY']))
            ->assertOk()
            ->json('analytics');
        $methods = collect($analytics['paymentMethods'])->keyBy('method');

        $this->assertEquals(100, $methods['Cash']['sales']);
        $this->assertEquals(100, $methods['GCash']['sales']);
        $this->assertEquals(200, $analytics['totalSales']);
        $this->assertEquals($analytics['totalSales'], collect($analytics['paymentMethods'])->sum('sales'));
    }

    private function makeUser(string $name, string $email, string $role, ?int $roleId = null): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'role_id' => $roleId,
        ]);
    }

    /** @return array<string, mixed> */
    private function registerPayload(array $overrides = []): array
    {
        return array_replace([
            'type' => 'take_out',
            'customer_name' => 'Integration test',
            'items' => [['item_id' => $this->menuItem->id, 'quantity' => 1]],
            'idempotency_key' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'amount_tendered' => 100,
        ], $overrides);
    }

    /** @return array{RestaurantTable, TableSession, Order} */
    private function createGuestTableOrder(): array
    {
        $table = RestaurantTable::query()->create([
            'table_number' => '12',
            'name' => 'Table 12',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);
        $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $session = TableSession::query()->where('table_id', $table->id)->whereNull('closed_at')->firstOrFail();
        $order = $session->orders()->with('items')->firstOrFail();

        return [$table, $session, $order];
    }
}
