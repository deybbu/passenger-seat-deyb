<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderReservationWorkflowTest extends TestCase
{
    private OrderService $orders;

    private MenuItem $menuItem;

    private Ingredient $ingredient;

    private User $cashier;

    private TableSession $tableSession;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'workflow_test',
            'database.connections.workflow_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('workflow_test');
        DB::setDefaultConnection('workflow_test');
        $this->createSchema();

        $this->cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@example.test',
            'password' => 'password',
            'role' => 'cashier',
        ]);

        $category = MenuCategory::create(['name' => 'Mains', 'is_active' => true]);
        $this->menuItem = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'Test meal',
            'slug' => 'test-meal',
            'price' => 100,
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->ingredient = Ingredient::create([
            'name' => 'Rice',
            'current_stock' => 10,
            'unit' => 'kg',
            'min_threshold' => 1,
        ]);

        DB::table('ingredient_menu_item')->insert([
            'menu_item_id' => $this->menuItem->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 1.5,
        ]);

        $this->tableSession = TableSession::create([
            'table_number' => '5',
            'public_token' => Str::random(64),
            'active_table_number' => '5',
            'guest_count' => 2,
            'opened_by' => $this->cashier->id,
            'opened_at' => now(),
            'expires_at' => now()->addHours(3),
        ]);

        $this->orders = app(OrderService::class);
    }

    public function test_customer_qr_ordering_uses_an_open_table_and_is_idempotent(): void
    {
        $this->get(route('customer.qr.show', ['table' => 'missing']))
            ->assertOk()
            ->assertSee("Table missing isn't open yet", false);
        $this->get(route('customer.qr.show'))
            ->assertOk()
            ->assertSee('Invalid QR code');
        $this->get(route('customer.qr.show', ['table' => '5']))
            ->assertOk()
            ->assertSee('Test meal')
            ->assertSee('data-place-url=', false);

        $payload = [
            'table_token' => $this->tableSession->public_token,
            'submission_token' => (string) Str::uuid(),
            'items' => [$this->menuItem->id => 2],
            'special_instructions' => 'No onions',
        ];

        $placed = $this->postJson(route('customer.qr.place'), $payload)
            ->assertCreated()
            ->assertJsonStructure(['reference', 'redirect']);
        $this->assertStringStartsWith('PSR-', $placed->json('reference'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);

        $this->postJson(route('customer.qr.place'), $payload)
            ->assertOk()
            ->assertJsonPath('reference', $placed->json('reference'));
        $this->assertDatabaseCount('orders', 1);

        $this->getJson(route('customer.qr.orders', ['tableSession' => $this->tableSession->public_token]))
            ->assertOk()
            ->assertJsonPath('orders.0.status', 'pending')
            ->assertJsonPath('orders.0.items.0.name', 'Test meal');

        $order = Order::query()->firstOrFail();
        $this->orders->transition($order, 'preparing', $this->cashier);

        $this->getJson(route('customer.qr.orders', ['tableSession' => $this->tableSession->public_token]))
            ->assertOk()
            ->assertJsonPath('orders.0.status', 'preparing');

        $this->postJson(route('customer.qr.bill', ['tableSession' => $this->tableSession->public_token]))
            ->assertOk();

        $this->getJson(route('customer.qr.orders', ['tableSession' => $this->tableSession->public_token]))
            ->assertJsonPath('bill_requested', true);
    }

    public function test_only_cashiers_can_manage_table_sessions(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('staff.tables.index'))
            ->assertOk()
            ->assertSee('Table sessions')
            ->assertSee('Open table QR')
            ->assertSee('No orders yet.');

        $kitchen = User::create([
            'name' => 'Kitchen',
            'email' => 'kitchen@example.test',
            'password' => 'password',
            'role' => 'kitchen',
        ]);

        $this->actingAs($kitchen)
            ->get(route('staff.tables.index'))
            ->assertForbidden();

        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('staff.tables.index'))
            ->assertForbidden();
    }

    public function test_kitchen_board_uses_real_orders_and_is_restricted_to_kitchen_staff(): void
    {
        $order = $this->placeOrder(2);
        $order->update(['special_instructions' => 'No onions']);
        $kitchen = User::create([
            'name' => 'Kitchen Staff',
            'email' => 'kitchen-board@example.test',
            'password' => 'password',
            'role' => 'kitchen',
        ]);

        $this->actingAs($kitchen)
            ->get(route('staff.kitchen.index'))
            ->assertOk()
            ->assertSee($order->reference_number)
            ->assertSee('Test meal')
            ->assertSee('No onions')
            ->assertSee('Ingredient stock')
            ->assertSee('3.000 kg reserved')
            ->assertDontSee('PS-0107');

        $this->get(route('staff.kitchen.board'))
            ->assertOk()
            ->assertSee($order->reference_number)
            ->assertSee('Pending');

        $this->get(route('staff.kitchen.inventory'))
            ->assertOk()
            ->assertSee('Rice')
            ->assertSee('Menu availability');

        $this->actingAs($this->cashier)
            ->get(route('staff.kitchen.index'))
            ->assertForbidden();
    }

    public function test_kitchen_status_actions_use_shared_transition_and_inventory_services(): void
    {
        $order = $this->placeOrder(2);
        $kitchen = User::create([
            'name' => 'Kitchen Staff',
            'email' => 'kitchen-actions@example.test',
            'password' => 'password',
            'role' => 'kitchen',
        ]);

        $this->actingAs($kitchen)
            ->patch(route('staff.orders.transition', $order), ['status' => 'preparing'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'preparing']);
        $this->assertDatabaseHas('inventory_logs', [
            'order_id' => $order->id,
            'ingredient_id' => $this->ingredient->id,
            'type' => 'stock_out',
            'quantity' => 3,
        ]);

        $this->patch(route('staff.orders.transition', $order), ['status' => 'ready'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready']);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'preparing',
            'to_status' => 'ready',
            'changed_by' => $kitchen->id,
        ]);
    }

    public function test_cashier_records_a_senior_discount_on_the_shared_order_and_receipt(): void
    {
        $order = $this->placeOrder(2);
        MenuItem::create([
            'menu_category_id' => $this->menuItem->menu_category_id,
            'name' => 'Unavailable meal',
            'slug' => 'unavailable-meal',
            'price' => 150,
            'is_active' => true,
            'is_available' => false,
        ]);

        $this->actingAs($this->cashier)
            ->get(route('staff.orders.create'))
            ->assertOk()
            ->assertSee('Passenger')
            ->assertSee('Test meal')
            ->assertSee('Unavailable meal');

        $registerItems = $this->get(route('staff.orders.create'))->viewData('menuItems');
        $unavailableItem = $registerItems->firstWhere('name', 'Unavailable meal');
        $this->assertFalse($unavailableItem['available']);
        $this->assertSame(0, $unavailableItem['stock']);

        $this->actingAs($this->cashier)
            ->get(route('staff.pos.index'))
            ->assertOk()
            ->assertSee('Unpaid bills')
            ->assertSee($order->reference_number)
            ->assertSee('Senior Citizen (20%)');

        $this->get(route('staff.pos.ledger'))
            ->assertOk()
            ->assertSee('Transaction ledger')
            ->assertSee('VAT exempt')
            ->assertSee($order->reference_number);

        $response = $this->actingAs($this->cashier)
            ->post(route('staff.pos.paid', $order), [
                'payment_method' => 'cash',
                'amount_tendered' => 200,
                'discount_type' => 'SC',
                'discount_cardholder_name' => 'Test Cardholder',
                'discount_id_number' => 'OSCA-12345',
                'discount_tin' => '123-456-789',
            ]);

        $response->assertRedirect(route('staff.pos.receipt', $order))
            ->assertSessionHas('success');

        $paidOrder = $order->fresh();
        $this->assertSame('pending', $paidOrder->status);
        $this->assertSame('paid', $paidOrder->payment_status);
        $this->assertSame(200.0, (float) $paidOrder->subtotal);
        $this->assertSame(20.0, (float) $paidOrder->discount_rate);
        $this->assertSame(35.71, (float) $paidOrder->discount_amount);
        $this->assertSame(142.86, (float) $paidOrder->total_amount);
        $this->assertSame(200.0, (float) $paidOrder->amount_tendered);
        $this->assertSame($this->cashier->id, $paidOrder->cashier_id);
        $this->assertSame('OSCA-12345', $paidOrder->discount_id_number);

        $this->actingAs($this->cashier)
            ->get(route('staff.pos.receipt', $order))
            ->assertOk()
            ->assertSee('VAT exemption')
            ->assertSee('Senior Citizen (20%)')
            ->assertSee('Test Cardholder')
            ->assertSee('OSCA-12345')
            ->assertSee('Change');

        $this->get(route('staff.pos.ledger'))
            ->assertOk()
            ->assertSee('Paid')
            ->assertSee('₱142.86');

        $this->post(route('staff.pos.paid', $order), [
            'payment_method' => 'cash',
            'amount_tendered' => 200,
        ])->assertStatus(409);
    }

    public function test_walk_in_register_includes_active_uncategorized_menu_items(): void
    {
        MenuItem::create([
            'menu_category_id' => null,
            'name' => 'Uncategorized meal',
            'slug' => 'uncategorized-meal',
            'price' => 125,
            'is_active' => true,
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->cashier)
            ->get(route('staff.orders.create'))
            ->assertOk()
            ->assertSee('data-menu-items=');

        $item = $response->viewData('menuItems')->firstWhere('name', 'Uncategorized meal');
        $this->assertSame('Uncategorized', $item['category']);
        $this->assertSame(125.0, $item['price']);
    }

    public function test_cashier_register_creates_and_pays_the_shared_order(): void
    {
        $response = $this->actingAs($this->cashier)
            ->post(route('staff.orders.store'), [
                'type' => 'dine_in',
                'table_number' => '5',
                'customer_name' => 'Test Guest',
                'items' => [$this->menuItem->id => 2],
                'payment_method' => 'cash',
                'amount_tendered' => 200,
                'discount_type' => 'SC',
                'discount_cardholder_name' => 'Test Cardholder',
                'discount_id_number' => 'OSCA-12345',
            ])
            ->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('staff.pos.receipt', $order));
        $this->assertSame('pending', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame(142.86, (float) $order->total_amount);
        $this->assertSame('OSCA-12345', $order->discount_id_number);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ]);
    }

    public function test_pos_tabs_use_real_orders_and_open_table_sessions(): void
    {
        $order = $this->placeOrder(1);

        $this->actingAs($this->cashier)
            ->get(route('staff.pos.orders'))
            ->assertOk()
            ->assertSee($order->reference_number)
            ->assertSee('Pending')
            ->assertSee('Cancel')
            ->assertDontSee('Mark Preparing')
            ->assertSee('aria-selected="true"', false)
            ->assertSee('action="'.route('staff.logout').'"', false)
            ->assertSee('>Sign out</button>', false);

        $this->get(route('staff.pos.index'))
            ->assertOk()
            ->assertSee('Unpaid bills')
            ->assertSee($order->reference_number)
            ->assertSee('data-pos-payment-form', false);

        $this->get(route('staff.pos.table-qr'))
            ->assertOk()
            ->assertSee('Table ordering (customer view)')
            ->assertSee('Test meal')
            ->assertSee($this->tableSession->public_token)
            ->assertSee('data-place-url="'.route('customer.qr.place').'"', false);
    }

    public function test_register_does_not_keep_an_order_when_payment_validation_fails(): void
    {
        $this->actingAs($this->cashier)
            ->post(route('staff.orders.store'), [
                'type' => 'take_out',
                'items' => [$this->menuItem->id => 1],
                'payment_method' => 'cash',
                'amount_tendered' => 99.99,
            ])
            ->assertSessionHasErrors('amount_tendered');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_ingredient_reservations', 0);
    }

    public function test_cashier_can_record_a_non_cash_promo_and_payment_errors_are_rejected(): void
    {
        $order = $this->placeOrder(1);

        $this->actingAs($this->cashier)
            ->post(route('staff.pos.paid', $order), [
                'payment_method' => 'gcash',
                'discount_type' => 'CUSTOM',
                'discount_rate' => 10,
            ])
            ->assertRedirect(route('staff.pos.receipt', $order));

        $paidOrder = $order->fresh();
        $this->assertSame('pending', $paidOrder->status);
        $this->assertSame('gcash', $paidOrder->payment_method);
        $this->assertSame(90.0, (float) $paidOrder->total_amount);
        $this->assertSame(90.0, (float) $paidOrder->amount_tendered);
        $this->assertSame(10.0, (float) $paidOrder->discount_amount);

        $orderNeedingId = $this->placeOrder(1);
        $this->post(route('staff.pos.paid', $orderNeedingId), [
            'payment_method' => 'card',
            'discount_type' => 'PWD',
        ])->assertSessionHasErrors(['discount_cardholder_name', 'discount_id_number']);
        $this->assertSame('unpaid', $orderNeedingId->fresh()->payment_status);

        $orderNeedingCash = $this->placeOrder(2);
        $this->post(route('staff.pos.paid', $orderNeedingCash), [
            'payment_method' => 'cash',
            'amount_tendered' => 199.99,
        ])->assertSessionHasErrors('amount_tendered');
        $this->assertSame('unpaid', $orderNeedingCash->fresh()->payment_status);
    }

    public function test_pos_analytics_uses_only_completed_paid_orders_and_real_sales(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('staff.pos.analytics'))
            ->assertOk()
            ->assertSee('No completed paid orders in the last 7 days.')
            ->assertSee('No category sales recorded this week.')
            ->assertSee('No item sales recorded today.')
            ->assertSee('No completed payments recorded today.');

        $kitchen = User::create([
            'name' => 'Kitchen Analytics',
            'email' => 'kitchen-analytics@example.test',
            'password' => 'password',
            'role' => 'kitchen',
        ]);
        $this->actingAs($kitchen)
            ->get(route('staff.pos.analytics'))
            ->assertForbidden();

        $cashOrder = $this->placeOrder(2);
        $this->completeOrder($cashOrder);
        $this->actingAs($this->cashier)
            ->post(route('staff.pos.paid', $cashOrder), [
                'payment_method' => 'cash',
                'amount_tendered' => 180,
                'discount_type' => 'CUSTOM',
                'discount_rate' => 10,
            ])
            ->assertRedirect(route('staff.pos.receipt', $cashOrder));

        $cardOrder = $this->placeOrder(1);
        $this->completeOrder($cardOrder);
        $this->post(route('staff.pos.paid', $cardOrder), [
            'payment_method' => 'card',
        ])->assertRedirect(route('staff.pos.receipt', $cardOrder));

        $pendingPaidOrder = $this->placeOrder(1);
        $this->post(route('staff.pos.paid', $pendingPaidOrder), [
            'payment_method' => 'gcash',
        ])->assertRedirect(route('staff.pos.receipt', $pendingPaidOrder));

        $this->get(route('staff.pos.analytics'))
            ->assertOk()
            ->assertSee('₱280.00')
            ->assertSee('Completed paid orders')
            ->assertSee('Average paid order')
            ->assertSee('₱140.00')
            ->assertSee('Mains')
            ->assertSee('Test meal')
            ->assertSee('Cash')
            ->assertSee('Card')
            ->assertDontSee('GCash');

        $admin = User::create([
            'name' => 'Analytics Manager',
            'email' => 'analytics-manager@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $this->actingAs($admin)
            ->get(route('staff.pos.analytics'))
            ->assertOk()
            ->assertSee('Analytics &amp; Reports', false);
    }

    public function test_orders_reserve_stock_and_only_deduct_when_preparation_starts(): void
    {
        $order = $this->placeOrder(2);

        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(3.0, (float) $order->ingredientReservations()->sum('quantity'));
        $this->assertSame('reserved', $order->ingredientReservations()->value('status'));

        try {
            $this->placeOrder(5);
            $this->fail('An order exceeding available stock was accepted.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('enough stock', $exception->getMessage());
        }

        $this->orders->transition($order, 'preparing', $this->cashier);

        $this->assertSame(7.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'order_id' => $order->id,
            'ingredient_id' => $this->ingredient->id,
            'type' => 'stock_out',
            'quantity' => 3,
        ]);
    }

    public function test_menu_availability_tracks_reservations_deductions_and_restocking(): void
    {
        $order = $this->placeOrder(6);

        $menuItem = MenuItem::query()
            ->withStockAvailability()
            ->findOrFail($this->menuItem->id);

        $this->assertFalse($menuItem->isOrderable());
        $this->get(route('menu'))
            ->assertOk()
            ->assertSee('Test meal')
            ->assertSee('Sold out');
        $this->get(route('customer.qr.show', ['table' => '5']))
            ->assertOk()
            ->assertDontSee('Test meal');
        $this->postJson(route('customer.qr.place'), [
            'table_token' => $this->tableSession->public_token,
            'submission_token' => (string) Str::uuid(),
            'items' => [$this->menuItem->id => 1],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 1);

        $this->actingAs($this->cashier)
            ->get(route('staff.orders.create'))
            ->assertOk();
        $registerItem = $this->get(route('staff.orders.create'))
            ->viewData('menuItems')
            ->firstWhere('id', $this->menuItem->id);
        $this->assertFalse($registerItem['available']);
        $this->assertSame(0, $registerItem['stock']);

        $kitchen = User::create([
            'name' => 'Kitchen Stock',
            'email' => 'kitchen-stock@example.test',
            'password' => 'password',
            'role' => 'kitchen',
        ]);

        $this->actingAs($kitchen)
            ->get(route('staff.kitchen.inventory'))
            ->assertOk()
            ->assertSee('Sold out (recipe stock)');

        $this->orders->transition($order, 'preparing', $this->cashier);
        $this->assertSame(1.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertTrue($this->ingredient->fresh()->isLowStock());
        $this->assertFalse(
            MenuItem::query()->withStockAvailability()->findOrFail($this->menuItem->id)->isOrderable()
        );
        $this->actingAs($kitchen)
            ->get(route('staff.kitchen.inventory'))
            ->assertOk()
            ->assertSee('Low stock')
            ->assertSee('Sold out (recipe stock)');

        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-stock@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.log', $this->ingredient), [
                'type' => 'stock_in',
                'quantity' => 1,
                'notes' => 'Restock for menu availability test',
            ])
            ->assertRedirect();

        $this->assertSame(2.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertTrue(
            MenuItem::query()->withStockAvailability()->findOrFail($this->menuItem->id)->isOrderable()
        );
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'stock_in',
            'quantity' => 1,
            'stock_before' => 1,
            'stock_after' => 2,
        ]);
    }

    public function test_manual_stock_out_cannot_consume_reserved_ingredients(): void
    {
        $order = $this->placeOrder(6);
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-reservation@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.log', $this->ingredient), [
                'type' => 'stock_out',
                'quantity' => 2,
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseMissing('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'stock_out',
        ]);

        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-waste@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.waste.store'), [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 2,
            ])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'waste',
        ]);

        $this->orders->transition($order, 'cancelled', $this->cashier);

        $this->actingAs($admin)
            ->post(route('admin.waste.store'), [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 2,
                'notes' => 'Damaged ingredient',
            ])
            ->assertSessionHas('status');
        $this->assertSame(8.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'waste',
            'quantity' => -2,
            'stock_before' => 10,
            'stock_after' => 8,
        ]);

        $this->post(route('admin.barcode.receive'), [
            'code' => 'Rice',
            'quantity' => 2,
        ])->assertSessionHas('status');
        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'stock_in',
            'quantity' => 2,
            'stock_before' => 8,
            'stock_after' => 10,
        ]);
    }

    public function test_cancelling_orders_releases_or_restores_their_stock_correctly(): void
    {
        $pendingOrder = $this->placeOrder(4);

        try {
            app(InventoryService::class)->logMovement(
                $this->ingredient,
                'stock_out',
                5,
                $this->cashier,
                'manual stock out',
            );
            $this->fail('A manual stock movement consumed stock reserved by a pending order.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('reserved', $exception->getMessage());
        }

        $this->orders->transition($pendingOrder, 'cancelled', $this->cashier);

        $this->assertSame('released', $pendingOrder->ingredientReservations()->value('status'));
        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);

        $preparingOrder = $this->placeOrder(2);
        $this->orders->transition($preparingOrder, 'preparing', $this->cashier);
        $this->orders->transition($preparingOrder, 'cancelled', $this->cashier);

        $this->assertSame('restored', $preparingOrder->ingredientReservations()->value('status'));
        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'order_id' => $preparingOrder->id,
            'type' => 'adjustment',
            'quantity' => 3,
        ]);
    }

    public function test_order_status_history_records_the_shared_workflow(): void
    {
        $order = $this->placeOrder(1);
        $this->orders->transition($order, 'preparing', $this->cashier);
        $this->orders->transition($order, 'ready', $this->cashier);
        $this->orders->transition($order, 'completed', $this->cashier);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(
            ['pending', 'preparing', 'ready', 'completed'],
            $order->statusHistories()->pluck('to_status')->all(),
        );

        $this->assertFalse($order->fresh()->canTransitionTo('completed'));
    }

    private function placeOrder(int $quantity): Order
    {
        $item = MenuItem::query()->with('ingredients')->findOrFail($this->menuItem->id);

        return $this->orders->place([
            'type' => 'dine_in',
            'table_number' => '5',
            'customer_name' => 'Table guest',
            'customer_phone' => null,
            'special_instructions' => null,
        ], collect([(object) [
            'item' => $item,
            'qty' => $quantity,
            'line_total' => 0,
        ]]));
    }

    private function completeOrder(Order $order): void
    {
        $this->orders->transition($order, 'preparing', $this->cashier);
        $this->orders->transition($order, 'ready', $this->cashier);
        $this->orders->transition($order, 'completed', $this->cashier);
    }

    private function createSchema(): void
    {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });

        Schema::create('orders', function ($table): void {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('type');
            $table->string('order_type')->nullable();
            $table->string('table_number')->nullable();
            $table->foreignId('table_session_id')->nullable();
            $table->string('submission_token')->nullable()->unique();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->text('special_instructions')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->string('status');
            $table->string('payment_status');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->decimal('amount_tendered', 10, 2)->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount_rate', 5, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('discount_cardholder_name')->nullable();
            $table->string('discount_id_number')->nullable();
            $table->string('discount_tin')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('cashier_id')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function ($table): void {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('menu_item_id')->nullable();
            $table->string('name');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('price', 8, 2);
            $table->decimal('line_total', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('menu_categories', function ($table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function ($table): void {
            $table->id();
            $table->foreignId('menu_category_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('ingredients', function ($table): void {
            $table->id();
            $table->string('name');
            $table->decimal('current_stock', 10, 3);
            $table->string('unit');
            $table->decimal('min_threshold', 10, 3)->default(0);
            $table->boolean('manual_override')->default(false);
            $table->timestamps();
        });

        Schema::create('ingredient_menu_item', function ($table): void {
            $table->foreignId('menu_item_id');
            $table->foreignId('ingredient_id');
            $table->decimal('quantity', 10, 3);
            $table->primary(['menu_item_id', 'ingredient_id']);
        });

        Schema::create('inventory_logs', function ($table): void {
            $table->id();
            $table->foreignId('ingredient_id');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('order_id')->nullable();
            $table->string('type');
            $table->decimal('quantity', 10, 3);
            $table->decimal('stock_before', 10, 3)->nullable();
            $table->decimal('stock_after', 10, 3)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function ($table): void {
            $table->id();
            $table->foreignId('order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('order_ingredient_reservations', function ($table): void {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('ingredient_id');
            $table->string('ingredient_name');
            $table->string('unit');
            $table->decimal('quantity', 12, 3);
            $table->string('status')->default('reserved');
            $table->timestamps();
        });

        Schema::create('table_sessions', function ($table): void {
            $table->id();
            $table->string('table_number');
            $table->string('public_token')->unique();
            $table->string('active_table_number')->nullable()->unique();
            $table->unsignedTinyInteger('guest_count')->nullable();
            $table->foreignId('opened_by')->nullable();
            $table->foreignId('closed_by')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('bill_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }
}
