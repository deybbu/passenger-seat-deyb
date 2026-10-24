<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryPackage;
use App\Models\InventoryStockLot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PreparationRecipe;
use App\Models\PurchaseOrder;
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
        InventoryStockLot::create([
            'ingredient_id' => $this->ingredient->id,
            'received_quantity' => 10,
            'remaining_quantity' => 10,
            'receipt_token' => 'opening-stock-test',
            'received_at' => now(),
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
            ->assertSee("This QR code isn't valid", false);
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
        $this->assertSame(7.0, (float) $this->ingredient->fresh()->current_stock);

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
            ->assertSee('data-orders-url=', false)
            ->assertSee('data-inventory-url=', false);

        $this->getJson(route('staff.kitchen.orders.data'))
            ->assertOk()
            ->assertJsonPath('orders.0.id', $order->reference_number)
            ->assertJsonPath('orders.0.sp', 'No onions');

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
            ->assertSee('POS &amp; Billing', false)
            ->assertSee('data-v="pos"', false)
            ->assertSee('window.POS_ENDPOINTS');

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
            ->assertSee('POS &amp; Billing', false)
            ->assertSee('data-v="pos"', false)
            ->assertSee('window.POS_ENDPOINTS');

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

    public function test_manager_can_record_a_non_cash_promo_and_payment_errors_are_rejected(): void
    {
        $order = $this->placeOrder(1);
        $manager = User::create([
            'name' => 'Promo Manager',
            'email' => 'promo-manager@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($manager)
            ->post(route('staff.pos.paid', $order), [
                'payment_method' => 'gcash',
                'reference_number' => 'GC-123',
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
        $manager = User::create([
            'name' => 'Analytics Discount Manager',
            'email' => 'analytics-discount-manager@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
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
        $this->actingAs($manager)
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
            'reference_number' => 'CARD-123',
        ])->assertRedirect(route('staff.pos.receipt', $cardOrder));

        $pendingPaidOrder = $this->placeOrder(1);
        $this->post(route('staff.pos.paid', $pendingPaidOrder), [
            'payment_method' => 'gcash',
            'reference_number' => 'GC-124',
        ])->assertRedirect(route('staff.pos.receipt', $pendingPaidOrder));

        $this->get(route('staff.pos.analytics'))
            ->assertOk()
            ->assertSee('₱380.00')
            ->assertSee('Completed paid orders')
            ->assertSee('Average paid order')
            ->assertSee('₱126.67')
            ->assertSee('Cash')
            ->assertSee('Card')
            ->assertSee('GCash');

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

    public function test_orders_deduct_stock_once_when_placed_and_reject_insufficient_stock(): void
    {
        $order = $this->placeOrder(2);

        $this->assertSame(7.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(3.0, (float) $order->ingredientReservations()->sum('quantity'));
        $this->assertSame('consumed', $order->ingredientReservations()->value('status'));
        $this->assertSame(1, DB::table('inventory_logs')->where('order_id', $order->id)->where('type', 'stock_out')->count());

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

    public function test_manager_recipe_settings_drive_pos_costs_par_and_yield_loss_requirements(): void
    {
        $admin = User::create([
            'name' => 'Recipe Manager',
            'email' => 'recipe-manager@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'recipe-settings-inventory@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);
        $settings = [
            'unit_cost' => 3.25,
            'yield_loss_percent' => 25,
            'min_threshold' => 11,
        ];

        $this->actingAs($inventoryStaff)
            ->patch(route('staff.inventory.recipe-settings', $this->ingredient), $settings)
            ->assertForbidden();
        $this->actingAs($admin)
            ->patch(route('staff.inventory.recipe-settings', $this->ingredient), $settings)
            ->assertRedirect(route('inventory.index', ['tab' => 'rcp']))
            ->assertSessionHas('success', 'Recipe cost and par settings saved for Rice.');

        $this->assertSame(3.25, (float) $this->ingredient->fresh()->unit_cost);
        $this->assertSame(25.0, (float) $this->ingredient->fresh()->yield_loss_percent);
        $this->assertSame(11.0, (float) $this->ingredient->fresh()->min_threshold);
        $this->assertTrue($this->ingredient->fresh()->isLowStock());
        $this->assertSame(2.0, $this->ingredient->fresh()->rawQuantityForPortion(1.5));
        $this->assertTrue($this->menuItem->fresh()->isOrderable());

        $this->actingAs($admin)
            ->get(route('inventory.index', ['tab' => 'rcp']))
            ->assertOk()
            ->assertSee('<body data-role="mgr">', false)
            ->assertSee('Manager / Owner')
            ->assertSee('data-tab="rcp"', false)
            ->assertSee('Recipe matrix, costs and par levels')
            ->assertSee('Test meal · 1.500 kg per serving')
            ->assertSee('₱6.50');

        $order = $this->placeOrder(2);
        $this->assertSame(4.0, (float) $order->ingredientReservations()->value('quantity'));
        $this->orders->transition($order, 'preparing', $this->cashier);
        $this->assertSame(6.0, (float) $this->ingredient->fresh()->current_stock);

        $kitchen = User::create([
            'name' => 'Kitchen Staff',
            'email' => 'yield-loss-kitchen@example.test',
            'password' => 'password',
            'role' => 'kitchen',
        ]);
        $this->actingAs($kitchen)
            ->get(route('staff.kitchen.index'))
            ->assertOk()
            ->assertSee('data-inventory-url=', false);
        $inventory = $this->getJson(route('staff.kitchen.inventory.data'))->assertOk();
        $this->assertSame(2.0, (float) $inventory->json('recipes.0.1.0.1'));
        $this->assertSame('Rice', $inventory->json('ingredients.0.n'));
        $this->assertSame(6.0, (float) $inventory->json('ingredients.0.s'));
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

        $this->assertSame(1.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'stock_out',
            'quantity' => 9,
            'order_id' => $order->id,
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
                'stage' => 'Prep / production',
                'reason' => 'Expired',
            ])
            ->assertSessionHasErrors('quantity');
        $this->assertDatabaseMissing('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'waste',
        ]);

        $this->orders->transition($order, 'cancelled', $this->cashier);
        $openingLot = InventoryStockLot::query()->where('receipt_token', 'opening-stock-test')->firstOrFail();
        $this->assertSame(10.0, (float) $openingLot->fresh()->remaining_quantity);

        $this->actingAs($admin)
            ->post(route('admin.waste.store'), [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 2,
                'stage' => 'Pre-consumer spoilage',
                'reason' => 'Dropped/Spilled',
            ])
            ->assertSessionHas('status');
        $this->assertSame(8.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'waste',
            'quantity' => -2,
            'stage' => 'Pre-consumer spoilage',
            'stock_before' => 10,
            'stock_after' => 8,
        ]);

        InventoryPackage::create([
            'ingredient_id' => $this->ingredient->id,
            'barcode' => 'RICE-001',
            'label' => 'Rice package',
            'package_quantity' => 2,
            'is_active' => true,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'reference' => 'PO-WORKFLOW-001',
            'supplier_name' => 'Workflow supplier',
            'status' => 'open',
            'created_by' => $admin->id,
            'ordered_at' => now(),
        ]);
        $orderItem = $purchaseOrder->items()->create([
            'ingredient_id' => $this->ingredient->id,
            'ordered_quantity' => 2,
            'received_quantity' => 0,
        ]);
        $this->postJson(route('admin.barcode.lookup'), ['barcode' => 'RICE-001'])
            ->assertOk()
            ->assertJsonPath('purchase_order.reference', 'PO-WORKFLOW-001');
        $this->assertSame(8.0, (float) $this->ingredient->fresh()->current_stock);
        $this->postJson(route('admin.barcode.receive'), [
            'barcode' => 'RICE-001',
            'purchase_order_item_id' => $orderItem->id,
            'quantity' => 3,
            'invoice_number' => 'INV-OVER-DELIVERY',
            'batch_number' => 'BATCH-OVER-DELIVERY',
            'expires_at' => today()->addMonth()->toDateString(),
            'receipt_token' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $this->assertSame(8.0, (float) $this->ingredient->fresh()->current_stock);
        $this->postJson(route('admin.barcode.receive'), [
            'barcode' => 'RICE-001',
            'purchase_order_item_id' => $orderItem->id,
            'quantity' => 2,
            'invoice_number' => 'INV-WORKFLOW-001',
            'batch_number' => 'BATCH-WORKFLOW-001',
            'expires_at' => today()->addMonth()->toDateString(),
            'storage_zone' => 'Dry Pantry',
            'receipt_token' => (string) Str::uuid(),
        ])->assertCreated();
        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'stock_in',
            'quantity' => 2,
            'stock_before' => 8,
            'stock_after' => 10,
        ]);
    }

    public function test_admin_can_generate_a_purchase_order_and_close_a_short_delivery(): void
    {
        $admin = User::create([
            'name' => 'Inventory Admin',
            'email' => 'purchase-orders@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $this->ingredient->update(['current_stock' => 0]);

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.store', $this->ingredient), [
                'supplier_name' => 'Test supplier',
            ])
            ->assertRedirect(route('admin.purchase-orders'));

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();
        $orderItem = $purchaseOrder->items()->firstOrFail();
        $this->assertSame('open', $purchaseOrder->status);
        $this->assertSame(2.0, (float) $orderItem->ordered_quantity);

        InventoryPackage::create([
            'ingredient_id' => $this->ingredient->id,
            'barcode' => 'RICE-SHORT',
            'label' => 'Rice package',
            'package_quantity' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.barcode.lookup'), ['barcode' => 'RICE-SHORT'])
            ->assertOk()
            ->assertJsonPath('purchase_order.remaining_quantity', 2);

        $this->actingAs($admin)
            ->postJson(route('admin.barcode.receive'), [
                'barcode' => 'RICE-SHORT',
                'purchase_order_item_id' => $orderItem->id,
                'quantity' => 1,
                'unit_cost' => 4.5,
                'invoice_number' => 'INV-SHORT-001',
                'batch_number' => 'BATCH-SHORT-001',
                'expires_at' => today()->addMonth()->toDateString(),
                'storage_zone' => 'Dry Pantry',
                'receipt_token' => (string) Str::uuid(),
            ])
            ->assertCreated()
            ->assertJsonPath('remaining_quantity', 1);

        $this->actingAs($admin)
            ->patch(route('admin.purchase-orders.close-short', $orderItem))
            ->assertSessionHas('status');
        $this->assertSame('closed_short', $purchaseOrder->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'Receiving',
            'subject_id' => $purchaseOrder->id,
        ]);
    }

    public function test_inventory_staff_page_uses_reference_tabs_and_sign_out(): void
    {
        $this->ingredient->update(['current_stock' => 0]);
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-page@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);

        $this->actingAs($inventoryStaff)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('<body data-role="staff">', false)
            ->assertSee('Kitchen Staff')
            ->assertDontSee('Manager / Owner')
            ->assertSee('Fresh intake')
            ->assertSee('Stock board')
            ->assertSee('Prep and waste')
            ->assertSee('History')
            ->assertDontSee('data-tab="rcp"', false)
            ->assertDontSee('Loss report (managers)')
            ->assertDontSee('Preparation recipes · managers')
            ->assertSee('Scan package')
            ->assertSee('Fresh goods')
            ->assertSee('Stock by expiry')
            ->assertSee('Raw to prep')
            ->assertSee('Recipe matrix, costs and par levels')
            ->assertSee('Movement history')
            ->assertSee('Rice')
            ->assertSee('Sign out')
            ->assertSee('data-notification-toggle', false)
            ->assertSee('aria-controls="inventoryNotifications"', false)
            ->assertSee('Out of Stock')
            ->assertSee('Restock required')
            ->assertSee('id="inventoryNotifications"', false)
            ->assertSee('action="'.route('staff.logout').'"', false);

        $this->post(route('staff.logout'))->assertRedirect(route('staff.login'));
        $this->assertGuest();
    }

    public function test_inventory_staff_can_assign_a_package_barcode_to_an_ingredient(): void
    {
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-barcode@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.barcode'), [
                'ingredient_id' => $this->ingredient->id,
                'barcode' => '0123456789012',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Barcode assigned to Rice.');

        $this->assertSame('0123456789012', $this->ingredient->fresh()->barcode);
        $this->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('data-barcode="0123456789012"', false)
            ->assertSee('0123456789012')
            ->assertSee('Register this package barcode');

        $anotherIngredient = Ingredient::create([
            'name' => 'Beans',
            'current_stock' => 5,
            'unit' => 'kg',
            'min_threshold' => 1,
        ]);
        $this->post(route('staff.inventory.barcode'), [
            'ingredient_id' => $anotherIngredient->id,
            'barcode' => '0123456789012',
        ])->assertSessionHasErrors('barcode');
        $this->assertNull($anotherIngredient->fresh()->barcode);
    }

    public function test_package_scan_returns_server_preview_without_changing_stock(): void
    {
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-scan@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);
        InventoryPackage::create([
            'ingredient_id' => $this->ingredient->id,
            'barcode' => '0123456789012',
            'label' => 'Rice 2 kg bag',
            'package_quantity' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($inventoryStaff)
            ->postJson(route('staff.inventory.receive.lookup'), ['barcode' => '0123456789012'])
            ->assertOk()
            ->assertJsonPath('ingredient.name', 'Rice')
            ->assertJsonPath('current_stock', 10)
            ->assertJsonPath('package_quantity', 2)
            ->assertJsonPath('expected_stock', 12);

        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_stock_lots', 1);
        $this->assertDatabaseCount('inventory_logs', 0);

        $this->actingAs($inventoryStaff)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Try sample scan')
            ->assertSee('data-sample-package', false)
            ->assertSee('data-code="0123456789012"', false);
    }

    public function test_inventory_staff_can_register_an_unknown_package_barcode_without_receiving_stock(): void
    {
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-package@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);

        $this->actingAs($inventoryStaff)
            ->postJson(route('staff.inventory.packages.store'), [
                'ingredient_id' => $this->ingredient->id,
                'barcode' => '9988776655443',
                'label' => 'Rice 3 kg sack',
                'package_quantity' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('package_quantity', 3)
            ->assertJsonPath('expected_stock', 13);

        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_packages', [
            'ingredient_id' => $this->ingredient->id,
            'barcode' => '9988776655443',
            'package_quantity' => 3,
        ]);
        $this->assertDatabaseCount('inventory_stock_lots', 1);
        $this->assertDatabaseCount('inventory_logs', 0);
    }

    public function test_confirmed_package_receipt_updates_stock_once_and_records_its_lot(): void
    {
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-receive@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);
        InventoryPackage::create([
            'ingredient_id' => $this->ingredient->id,
            'barcode' => '0123456789012',
            'label' => 'Rice 2 kg bag',
            'package_quantity' => 2,
            'is_active' => true,
        ]);
        $payload = [
            'barcode' => '0123456789012',
            'receipt_token' => (string) Str::uuid(),
            'batch_number' => 'BATCH-42',
            'expires_at' => '2027-05-01 00:00:00',
            'storage_zone' => 'Dry Pantry Room',
        ];

        $this->actingAs($inventoryStaff)
            ->postJson(route('staff.inventory.receive.confirm'), $payload)
            ->assertCreated()
            ->assertJsonPath('current_stock', 10)
            ->assertJsonPath('package_quantity', 2)
            ->assertJsonPath('expected_stock', 12);

        $this->assertSame(12.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_stock_lots', [
            'ingredient_id' => $this->ingredient->id,
            'batch_number' => 'BATCH-42',
            'expires_at' => '2027-05-01 00:00:00',
            'received_quantity' => 2,
            'remaining_quantity' => 2,
            'received_by' => $inventoryStaff->id,
            'storage_zone' => 'Dry Pantry Room',
        ]);
        $lot = InventoryStockLot::query()->where('receipt_token', $payload['receipt_token'])->firstOrFail();
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'stock_lot_id' => $lot->id,
            'user_id' => $inventoryStaff->id,
            'type' => 'stock_in',
            'quantity' => 2,
            'stock_before' => 10,
            'stock_after' => 12,
        ]);

        $this->postJson(route('staff.inventory.receive.confirm'), $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'This receipt was already confirmed. Scan the package again for a new delivery.');

        $this->assertSame(12.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_stock_lots', 2);
        $this->assertDatabaseCount('inventory_logs', 1);
    }

    public function test_unknown_barcode_is_rejected_without_changing_stock(): void
    {
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-unknown@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);

        $this->actingAs($inventoryStaff)
            ->postJson(route('staff.inventory.receive.lookup'), ['barcode' => 'UNKNOWN-42'])
            ->assertNotFound()
            ->assertJsonPath('can_register', true);

        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_stock_lots', 1);
    }

    public function test_fresh_goods_receipt_creates_a_real_ingredient_lot_and_movement(): void
    {
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'fresh-goods@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);
        $expiryDate = today()->addDays(30)->toDateString();

        $freshReceipt = [
            'name' => 'Fresh squid',
            'category' => 'Seafood',
            'quantity' => 2.5,
            'unit' => 'kg',
            'batch_number' => 'SQ-2026-05',
            'expires_at' => $expiryDate,
            'storage_zone' => 'Chiller',
            'receipt_token' => (string) Str::uuid(),
        ];

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.receive.fresh'), $freshReceipt)
            ->assertRedirect(route('inventory.index', ['tab' => 'dash']))
            ->assertSessionHas('success', 'Received 2.5 kg of Fresh squid.');

        $freshIngredient = Ingredient::query()->where('name', 'Fresh squid')->firstOrFail();
        $this->assertSame(2.5, (float) $freshIngredient->current_stock);
        $lot = InventoryStockLot::query()->where('ingredient_id', $freshIngredient->id)->firstOrFail();
        $this->assertSame('SQ-2026-05', $lot->batch_number);
        $this->assertSame('Chiller', $lot->storage_zone);
        $this->assertSame(2.5, (float) $lot->remaining_quantity);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $freshIngredient->id,
            'stock_lot_id' => $lot->id,
            'type' => 'stock_in',
            'quantity' => 2.5,
            'user_id' => $inventoryStaff->id,
        ]);
        $this->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Stock by expiry (oldest used first)')
            ->assertSee('Fresh squid')
            ->assertSee('SQ-2026-05')
            ->assertSee('Usable unexpired stock: 2.500 kg');

        $this->post(route('staff.inventory.receive.fresh'), $freshReceipt)
            ->assertRedirect(route('inventory.index', ['tab' => 'fresh']))
            ->assertSessionHasErrors('fresh');
        $this->assertSame(2.5, (float) $freshIngredient->fresh()->current_stock);
        $this->assertSame(1, InventoryStockLot::query()->where('ingredient_id', $freshIngredient->id)->count());
    }

    public function test_stock_out_uses_unexpired_lots_in_fefo_order_and_skips_expired_lots(): void
    {
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'fefo@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);
        InventoryStockLot::query()->where('ingredient_id', $this->ingredient->id)->delete();
        $expiredLot = InventoryStockLot::create([
            'ingredient_id' => $this->ingredient->id,
            'batch_number' => 'EXPIRED',
            'expires_at' => today()->subDay(),
            'received_quantity' => 3,
            'remaining_quantity' => 3,
            'receipt_token' => 'fefo-expired',
        ]);
        $firstUsableLot = InventoryStockLot::create([
            'ingredient_id' => $this->ingredient->id,
            'batch_number' => 'FIRST-EXPIRY',
            'expires_at' => today()->addDay(),
            'received_quantity' => 4,
            'remaining_quantity' => 4,
            'receipt_token' => 'fefo-first',
        ]);
        $secondUsableLot = InventoryStockLot::create([
            'ingredient_id' => $this->ingredient->id,
            'batch_number' => 'SECOND-EXPIRY',
            'expires_at' => today()->addDays(8),
            'received_quantity' => 6,
            'remaining_quantity' => 6,
            'receipt_token' => 'fefo-second',
        ]);
        $this->ingredient->update(['current_stock' => 13]);

        app(InventoryService::class)->logMovement(
            $this->ingredient,
            'stock_out',
            5,
            $inventoryStaff,
            'Kitchen use',
        );

        $this->assertSame(8.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(3.0, (float) $expiredLot->fresh()->remaining_quantity);
        $this->assertSame(0.0, (float) $firstUsableLot->fresh()->remaining_quantity);
        $this->assertSame(5.0, (float) $secondUsableLot->fresh()->remaining_quantity);
        $this->assertDatabaseHas('inventory_logs', [
            'stock_lot_id' => $firstUsableLot->id,
            'type' => 'stock_out',
            'quantity' => 4,
            'stock_before' => 13,
            'stock_after' => 9,
        ]);
        $this->assertDatabaseHas('inventory_logs', [
            'stock_lot_id' => $secondUsableLot->id,
            'type' => 'stock_out',
            'quantity' => 1,
            'stock_before' => 9,
            'stock_after' => 8,
        ]);
    }

    public function test_menu_availability_does_not_offer_expired_lot_stock(): void
    {
        InventoryStockLot::query()->where('ingredient_id', $this->ingredient->id)->update([
            'expires_at' => today()->subDay(),
        ]);

        $this->assertFalse($this->menuItem->isOrderable());
    }

    public function test_inventory_staff_can_record_waste_with_a_reason(): void
    {
        $this->ingredient->update(['unit_cost' => 3.5]);
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-waste@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.waste'), [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 2,
                'notes' => 'Reason is missing',
            ])
            ->assertSessionHasErrors('reason');
        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.log', $this->ingredient), [
                'type' => 'waste',
                'quantity' => 2,
                'notes' => 'Attempt to skip the reason field',
            ])
            ->assertSessionHasErrors('type');
        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.waste'), [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 2,
                'reason' => 'Pre-Cook Spoilage',
                'notes' => 'Spoiled during storage',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Waste recorded. Estimated cost: ₱7.00.')
            ->assertSessionHas('waste_estimate', 7.0);

        $this->assertSame(8.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'user_id' => $inventoryStaff->id,
            'type' => 'waste',
            'quantity' => -2,
            'reason' => 'Pre-Cook Spoilage',
            'notes' => 'Spoiled during storage',
            'cost_amount' => 7,
        ]);

        $this->get(route('inventory.index'))
            ->assertOk()
            ->assertDontSee('Loss report (managers)');

        $admin = User::create([
            'name' => 'Inventory Manager',
            'email' => 'inventory-manager@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.waste'))
            ->assertOk()
            ->assertSee('id="view"', false);

        $this->actingAs($admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('<body data-role="mgr">', false)
            ->assertSee('Manager / Owner')
            ->assertSee('data-tab="rcp"', false)
            ->assertSee('Loss report (managers)')
            ->assertSee('Spoiled during storage')
            ->assertSee('All-time known estimated cost: ₱7.00.');
    }

    public function test_manager_can_configure_and_inventory_staff_can_record_preparation(): void
    {
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-prep@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);
        $preparedIngredient = Ingredient::create([
            'name' => 'Prepared rice',
            'current_stock' => 0,
            'unit' => 'kg',
            'min_threshold' => 0,
        ]);
        $recipePayload = [
            'output_ingredient_id' => $preparedIngredient->id,
            'yield_quantity' => 0.8,
            'lines' => [
                ['ingredient_id' => $this->ingredient->id, 'quantity' => 2],
            ],
        ];

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.preparation-recipes.store'), $recipePayload)
            ->assertForbidden();

        $admin = User::create([
            'name' => 'Inventory Manager',
            'email' => 'inventory-prep-manager@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $this->actingAs($admin)
            ->post(route('staff.inventory.preparation-recipes.store'), $recipePayload)
            ->assertRedirect(route('inventory.index', ['tab' => 'prep']))
            ->assertSessionHas('success', 'Preparation recipe saved.');
        $recipe = PreparationRecipe::query()->with('lines')->firstOrFail();

        $earlyLot = InventoryStockLot::create([
            'ingredient_id' => $this->ingredient->id,
            'batch_number' => 'EARLY-RAW',
            'expires_at' => today()->addDay(),
            'received_quantity' => 2,
            'remaining_quantity' => 2,
            'receipt_token' => 'prep-early-raw',
            'storage_zone' => 'Chiller',
        ]);
        $this->ingredient->update(['current_stock' => 12]);

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.prepare'), [
                'preparation_recipe_id' => $recipe->id,
                'batches' => 1,
            ])
            ->assertRedirect(route('inventory.index', ['tab' => 'prep']))
            ->assertSessionHas('success', 'Prepared 0.8 kg of Prepared rice.');

        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(0.0, (float) $earlyLot->fresh()->remaining_quantity);
        $this->assertSame(0.8, (float) $preparedIngredient->fresh()->current_stock);
        $outputLot = InventoryStockLot::query()
            ->where('ingredient_id', $preparedIngredient->id)
            ->firstOrFail();
        $this->assertSame(today()->addDay()->toDateString(), $outputLot->expires_at->toDateString());
        $this->assertSame('Chiller', $outputLot->storage_zone);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'stock_lot_id' => $earlyLot->id,
            'user_id' => $inventoryStaff->id,
            'type' => 'prep_out',
            'quantity' => -2,
        ]);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $preparedIngredient->id,
            'stock_lot_id' => $outputLot->id,
            'user_id' => $inventoryStaff->id,
            'type' => 'prep_in',
            'quantity' => 0.8,
        ]);

        $this->get(route('inventory.index', ['tab' => 'log']))
            ->assertOk()
            ->assertSee('Movement history')
            ->assertSee('Prep out')
            ->assertSee('Prep in');
    }

    public function test_inventory_staff_can_save_a_zero_physical_count(): void
    {
        $inventoryStaff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-count@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);

        $this->actingAs($inventoryStaff)
            ->post(route('staff.inventory.log', $this->ingredient), [
                'type' => 'adjustment',
                'quantity' => 0,
                'notes' => 'Empty shelf count',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Stock count saved.');

        $this->assertSame(0.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'user_id' => $inventoryStaff->id,
            'type' => 'adjustment',
            'quantity' => -10,
            'stock_before' => 10,
            'stock_after' => 0,
        ]);
    }

    public function test_cancelling_before_preparation_restores_stock_and_after_start_records_waste(): void
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
            $this->assertStringContainsString('stock', strtolower($exception->getMessage()));
        }

        $this->orders->transition($pendingOrder, 'cancelled', $this->cashier);

        $this->assertSame('restored', $pendingOrder->ingredientReservations()->value('status'));
        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);

        $preparingOrder = $this->placeOrder(2);
        $this->orders->transition($preparingOrder, 'preparing', $this->cashier);
        $this->orders->transition($preparingOrder, 'cancelled', $this->cashier);

        $this->assertSame('wasted', $preparingOrder->ingredientReservations()->value('status'));
        $this->assertSame(7.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'order_id' => $preparingOrder->id,
            'type' => 'waste',
            'quantity' => -3,
        ]);
    }

    public function test_order_status_history_records_the_shared_workflow(): void
    {
        $order = $this->placeOrder(1);
        $this->orders->transition($order, 'preparing', $this->cashier);
        $this->orders->transition($order, 'ready', $this->cashier);
        $this->orders->transition($order, 'served', $this->cashier);

        $this->assertSame('served', $order->fresh()->status);
        $this->assertSame(
            ['pending', 'preparing', 'ready', 'served'],
            $order->statusHistories()->pluck('to_status')->all(),
        );

        $this->assertFalse($order->fresh()->canTransitionTo('served'));
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
        $this->orders->transition($order, 'served', $this->cashier);
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
            $table->timestamp('held_at')->nullable()->index();
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
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('category', 80)->nullable();
            $table->decimal('current_stock', 10, 3);
            $table->string('unit');
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('min_threshold', 10, 3)->default(0);
            $table->decimal('yield_loss_percent', 5, 2)->default(0);
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
            $table->string('reason', 80)->nullable();
            $table->string('stage', 40)->nullable();
            $table->decimal('cost_amount', 12, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_packages', function ($table): void {
            $table->id();
            $table->foreignId('ingredient_id');
            $table->string('barcode', 64)->unique();
            $table->string('label', 120);
            $table->decimal('package_quantity', 12, 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_stock_lots', function ($table): void {
            $table->id();
            $table->foreignId('ingredient_id');
            $table->foreignId('package_id')->nullable();
            $table->string('batch_number', 120)->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('received_quantity', 12, 3);
            $table->decimal('remaining_quantity', 12, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->string('receipt_token', 64)->unique();
            $table->foreignId('received_by')->nullable();
            $table->foreignId('purchase_order_item_id')->nullable();
            $table->string('supplier_invoice_number', 80)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('storage_zone', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function ($table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('supplier_name', 150);
            $table->string('status', 24)->default('open');
            $table->string('invoice_number', 80)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('ingredient_id');
            $table->decimal('ordered_quantity', 12, 3);
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->decimal('expected_unit_cost', 12, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 255);
            $table->string('module', 80);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('preparation_recipes', function ($table): void {
            $table->id();
            $table->foreignId('output_ingredient_id')->unique();
            $table->decimal('yield_quantity', 12, 3);
            $table->timestamps();
        });

        Schema::create('preparation_recipe_lines', function ($table): void {
            $table->id();
            $table->foreignId('preparation_recipe_id');
            $table->foreignId('ingredient_id');
            $table->decimal('quantity', 12, 3);
            $table->timestamps();
        });

        Schema::table('inventory_logs', function ($table): void {
            $table->foreignId('stock_lot_id')->nullable();
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

        Schema::table('orders', function ($table): void {
            $table->decimal('vat_exemption', 10, 2)->default(0);
        });

        Schema::table('table_sessions', function ($table): void {
            $table->decimal('vat_exemption', 10, 2)->default(0);
        });

        Schema::create('table_session_payments', function ($table): void {
            $table->id();
            $table->foreignId('table_session_id');
            $table->string('type', 16);
            $table->string('payment_method', 20);
            $table->string('payment_reference', 60)->unique();
            $table->decimal('amount', 10, 2);
            $table->decimal('amount_tendered', 10, 2)->nullable();
            $table->decimal('change_given', 10, 2)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('vat_exemption', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_rate', 5, 2)->nullable();
            $table->foreignId('performed_by')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
