<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuModifierGroup;
use App\Models\MenuModifierOption;
use App\Models\Ingredient;
use App\Models\InventoryStockLot;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\TableSessionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerTableOrderPageTest extends TestCase
{
    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'table_order_page_test',
            'database.connections.table_order_page_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('table_order_page_test');
        DB::setDefaultConnection('table_order_page_test');
        $this->artisan('migrate:fresh', ['--database' => 'table_order_page_test'])
            ->assertExitCode(0);
        $this->withoutVite();

        $category = MenuCategory::query()->create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
        ]);
        $this->menuItem = MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Database meal',
            'slug' => 'database-meal',
            'description' => 'A menu item loaded from the database.',
            'price' => 100,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    public function test_valid_table_token_shows_table_and_database_menu_without_login(): void
    {
        $table = RestaurantTable::query()->create([
            'table_number' => '12',
            'name' => 'Table 12',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);
        MenuItemVariant::query()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Large',
            'price' => 145,
            'is_active' => true,
        ]);
        $this->menuItem->update(['image_path' => 'menu/database-meal.jpg']);
        $ingredient = Ingredient::query()->create([
            'name' => 'Rice',
            'current_stock' => 5,
            'unit' => 'kg',
            'min_threshold' => 1,
        ]);
        $this->menuItem->ingredients()->attach($ingredient->id, ['quantity' => 1]);
        InventoryStockLot::query()->create([
            'ingredient_id' => $ingredient->id,
            'expires_at' => today()->addDays(30),
            'received_quantity' => 5,
            'remaining_quantity' => 5,
            'receipt_token' => (string) Str::uuid(),
            'received_at' => now(),
        ]);
        $group = MenuModifierGroup::query()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Add-ons',
            'is_required' => false,
            'sort_order' => 1,
        ]);
        MenuModifierOption::query()->create([
            'menu_modifier_group_id' => $group->id,
            'name' => 'Extra cheese',
            'price_delta' => 15,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $orderUrl = route('customer.order.submit', ['tableToken' => $table->qr_token], false);
        $sessionUrl = route('customer.order.session', ['tableToken' => $table->qr_token], false);
        $billRequestUrl = route('customer.order.bill-request', ['tableToken' => $table->qr_token], false);
        $billCancelUrl = route('customer.order.bill-request.cancel', ['tableToken' => $table->qr_token], false);

        $this->get(route('customer.order.show', ['tableToken' => $table->qr_token]).'?table=5')
            ->assertOk()
            ->assertSee('Table 12')
            ->assertDontSee('Table 5')
            ->assertSee('Database meal')
            ->assertSee('Large')
            ->assertSee('Add-ons')
            ->assertSee('Extra cheese')
            ->assertSee('Rice')
            ->assertSee('database-meal.jpg')
            ->assertSee('window.PS_MENU =', false)
            ->assertSee('window.PS_TABLE_NUMBER', false)
            ->assertSee(str_replace('/', '\\/', $orderUrl), false)
            ->assertSee(str_replace('/', '\\/', $sessionUrl), false)
            ->assertSee(str_replace('/', '\\/', $billRequestUrl), false)
            ->assertSee(str_replace('/', '\\/', $billCancelUrl), false)
            ->assertDontSee('ps_table')
            ->assertDontSee('admin-user');

        $this->getJson(route('customer.order.session', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertJsonPath('available_item_ids.0', $this->menuItem->id);
    }

    public function test_customer_menu_hides_items_with_required_modifier_groups(): void
    {
        $table = $this->createEnabledTable();
        $group = MenuModifierGroup::query()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Required option',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        MenuModifierOption::query()->create([
            'menu_modifier_group_id' => $group->id,
            'name' => 'Choose one',
            'price_delta' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('customer.order.show', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertDontSee('Database meal');
    }

    public function test_invalid_and_inactive_table_tokens_show_the_invalid_qr_screen(): void
    {
        $this->get(route('customer.order.show', ['tableToken' => Str::random(64)]))
            ->assertOk()
            ->assertSee("This QR code isn't valid", false)
            ->assertDontSee('Database meal');

        $table = RestaurantTable::query()->create([
            'table_number' => '13',
            'name' => 'Table 13',
            'is_enabled' => false,
            'qr_token' => Str::random(64),
        ]);

        $this->get(route('customer.order.show', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertSee("This QR code isn't valid", false)
            ->assertDontSee('Database meal');
    }

    public function test_cashier_can_toggle_table_qr_and_admin_uses_the_same_state(): void
    {
        $table = $this->createEnabledTable();
        $cashier = User::query()->create([
            'name' => 'QR Cashier', 'email' => 'qr-cashier-toggle@example.test', 'password' => 'password', 'role' => 'cashier',
        ]);

        $this->actingAs($cashier)
            ->post(route('staff.pos.table-qr.toggle', ['restaurantTable' => $table->table_number]))
            ->assertRedirect(route('staff.pos.table-qr'));
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id, 'is_enabled' => false]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $cashier->id,
            'module' => 'Table QR',
            'subject_id' => $table->id,
        ]);
        $this->get(route('staff.pos.table-qr'))->assertOk()->assertSee('QR off')->assertSee('Enable QR');

        $admin = User::query()->create([
            'name' => 'QR Admin', 'email' => 'qr-admin-toggle@example.test', 'password' => 'password', 'role' => 'admin',
        ]);
        $this->actingAs($admin)
            ->post(route('staff.pos.table-qr.toggle', ['restaurantTable' => $table->table_number]))
            ->assertRedirect(route('staff.pos.table-qr'));
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id, 'is_enabled' => true]);
    }

    public function test_kitchen_and_unauthenticated_customer_cannot_toggle_table_qr(): void
    {
        $table = $this->createEnabledTable();
        $url = route('staff.pos.table-qr.toggle', ['restaurantTable' => $table->table_number]);
        $kitchen = User::query()->create([
            'name' => 'Kitchen User', 'email' => 'qr-kitchen-toggle@example.test', 'password' => 'password', 'role' => 'kitchen',
        ]);

        $this->actingAs($kitchen)->post($url)->assertForbidden();
        auth()->logout();
        $this->post($url)->assertRedirect(route('staff.login'));
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id, 'is_enabled' => true]);
    }

    public function test_disabled_table_cannot_submit_a_customer_qr_order(): void
    {
        $table = $this->createEnabledTable();
        $cashier = User::query()->create([
            'name' => 'QR Cashier', 'email' => 'qr-cashier-disable@example.test', 'password' => 'password', 'role' => 'cashier',
        ]);
        $this->actingAs($cashier)->post(route('staff.pos.table-qr.toggle', [
            'restaurantTable' => $table->table_number,
        ]));

        $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]],
        ])->assertNotFound()->assertJsonPath('message', "This QR code isn't valid.");
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pos_table_status_cards_are_sorted_by_natural_table_number(): void
    {
        foreach (['1', '10', '2'] as $number) {
            RestaurantTable::query()->create([
                'table_number' => $number,
                'name' => 'Table '.$number,
                'is_enabled' => true,
                'qr_token' => Str::random(64),
            ]);
        }

        $content = $this->actingAs(User::query()->create([
            'name' => 'Sort Cashier', 'email' => 'sort-cashier@example.test', 'password' => 'password', 'role' => 'cashier',
        ]))->get(route('staff.pos.table-qr'))->assertOk()->getContent();

        $tableOne = strpos($content, '<strong>Table 1</strong>');
        $tableTwo = strpos($content, '<strong>Table 2</strong>');
        $tableTen = strpos($content, '<strong>Table 10</strong>');
        $this->assertNotFalse($tableOne);
        $this->assertNotFalse($tableTwo);
        $this->assertNotFalse($tableTen);
        $this->assertTrue($tableOne < $tableTwo && $tableTwo < $tableTen);
    }

    public function test_customer_status_is_current_and_scoped_to_the_table_token(): void
    {
        $table = $this->createEnabledTable();
        $otherTable = RestaurantTable::query()->create([
            'table_number' => '13',
            'name' => 'Table 13',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);
        $submission = $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();
        $order = Order::query()->where('reference_number', $submission->json('reference'))->firstOrFail();
        $statusUrl = route('customer.order.status', [
            'tableToken' => $table->qr_token,
            'reference' => $order->reference_number,
        ]);

        $this->getJson($statusUrl)
            ->assertOk()
            ->assertExactJson([
                'reference' => $order->reference_number,
                'status' => 'Pending',
            ]);

        $order->update(['status' => 'preparing']);
        $this->getJson($statusUrl)
            ->assertOk()
            ->assertExactJson([
                'reference' => $order->reference_number,
                'status' => 'Preparing',
            ]);

        $this->getJson(route('customer.order.status', [
            'tableToken' => $otherTable->qr_token,
            'reference' => $order->reference_number,
        ]))->assertNotFound();

        $table->update(['is_enabled' => false]);
        $this->getJson($statusUrl)->assertNotFound();
    }

    public function test_plain_table_query_does_not_resolve_a_public_table(): void
    {
        $this->get(route('customer.qr.show', ['table' => '12']))
            ->assertOk()
            ->assertSee("Table 12 isn't open yet", false)
            ->assertDontSee('Database meal');
    }

    public function test_table_order_uses_database_prices_and_ignores_tampered_totals(): void
    {
        $table = $this->createEnabledTable();

        $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
            'idempotency_key' => (string) Str::uuid(),
            'special_instructions' => 'No onions',
            'subtotal' => 0.01,
            'total' => 0.01,
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
                'price' => 0.01,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('subtotal', 100)
            ->assertJsonPath('vat_amount', 10.71)
            ->assertJsonPath('total', 100)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('session.status', 'open')
            ->assertJsonPath('session.order_count', 1)
            ->assertJsonPath('session.total', 100)
            ->assertJsonPath('session.orders.0.batch_number', 1)
            ->assertJsonPath('session.orders.0.items.0.name', 'Database meal');

        $order = Order::query()->with('items')->firstOrFail();
        $this->assertSame('table_qr', $order->source);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('12', $order->table_number);
        $this->assertSame(100.0, (float) $order->items->first()->price);
        $this->assertSame('No onions', $order->special_instructions);
    }

    public function test_table_order_prices_the_selected_variant_and_modifiers_from_database(): void
    {
        $table = $this->createEnabledTable();
        $variant = MenuItemVariant::query()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Large',
            'price' => 145,
            'is_active' => true,
        ]);
        $group = MenuModifierGroup::query()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Add-ons',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $modifier = MenuModifierOption::query()->create([
            'menu_modifier_group_id' => $group->id,
            'name' => 'Extra cheese',
            'price_delta' => 15,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'variant_id' => $variant->id,
                'modifier_option_ids' => [$modifier->id],
                'quantity' => 1,
                'price' => 0.01,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('subtotal', 160)
            ->assertJsonPath('vat_amount', 17.14)
            ->assertJsonPath('total', 160);

        $orderItem = Order::query()->firstOrFail()->items()->firstOrFail();
        $this->assertSame(160.0, (float) $orderItem->price);
        $this->assertSame($variant->id, $orderItem->menu_item_variant_id);
        $this->assertSame('Extra cheese', $orderItem->modifier_snapshot[0]['name']);
    }

    public function test_table_qr_orders_reserve_and_consume_variant_recipe_stock_fifo(): void
    {
        $table = $this->createEnabledTable();
        $ingredient = Ingredient::query()->create([
            'name' => 'Rice',
            'current_stock' => 4,
            'unit' => 'kg',
            'min_threshold' => 1,
        ]);
        $this->menuItem->ingredients()->attach($ingredient->id, ['quantity' => 1.5]);
        $firstLot = InventoryStockLot::query()->create([
            'ingredient_id' => $ingredient->id,
            'expires_at' => today()->addDays(2),
            'received_quantity' => 2,
            'remaining_quantity' => 2,
            'receipt_token' => (string) Str::uuid(),
            'received_at' => now(),
        ]);
        $secondLot = InventoryStockLot::query()->create([
            'ingredient_id' => $ingredient->id,
            'expires_at' => today()->addDays(4),
            'received_quantity' => 2,
            'remaining_quantity' => 2,
            'receipt_token' => (string) Str::uuid(),
            'received_at' => now(),
        ]);
        $variant = MenuItemVariant::query()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Large',
            'price' => 145,
            'portion_factor' => 2,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();
        $order = Order::query()->where('reference_number', $response->json('reference'))->firstOrFail();

        $this->assertSame(3.0, (float) $order->ingredientReservations()->sum('quantity'));
        $this->assertSame(4.0, (float) $ingredient->fresh()->current_stock);
        $this->getJson(route('customer.order.session', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertJsonPath('available_item_ids', []);
        $this->get(route('customer.order.show', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertDontSee('Database meal');
        $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
        ])->assertStatus(409);

        $kitchen = User::query()->create([
            'name' => 'Kitchen Staff',
            'email' => 'table-stock-kitchen@example.test',
            'password' => 'password',
            'role' => 'kitchen',
        ]);
        $this->actingAs($kitchen)
            ->patch(route('staff.orders.transition', ['order' => $order]), ['status' => 'preparing'])
            ->assertRedirect();

        $this->assertSame(1.0, (float) $ingredient->fresh()->current_stock);
        $this->assertSame(0.0, (float) $firstLot->fresh()->remaining_quantity);
        $this->assertSame(1.0, (float) $secondLot->fresh()->remaining_quantity);
        $this->assertDatabaseHas('inventory_logs', [
            'order_id' => $order->id,
            'stock_lot_id' => $firstLot->id,
            'type' => 'stock_out',
            'quantity' => 2,
            'notes' => 'Order '.$order->reference_number,
        ]);
        $this->assertDatabaseHas('inventory_logs', [
            'order_id' => $order->id,
            'stock_lot_id' => $secondLot->id,
            'type' => 'stock_out',
            'quantity' => 1,
            'notes' => 'Order '.$order->reference_number,
        ]);

        $admin = User::query()->create([
            'name' => 'Inventory Admin',
            'email' => 'table-stock-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $alerts = $this->actingAs($admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->json('alerts');
        $this->assertTrue(collect($alerts)->contains(
            fn (array $alert): bool => $alert['type'] === 'stock'
                && str_contains($alert['body'], 'Rice'),
        ));
    }

    public function test_table_order_rejects_an_unavailable_menu_item(): void
    {
        $table = $this->createEnabledTable();
        $this->menuItem->update(['is_available' => false]);

        $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ]],
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'One or more selected dishes are no longer available.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_repeated_table_order_submission_returns_the_existing_order(): void
    {
        $table = $this->createEnabledTable();
        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
            ]],
        ];
        $url = route('customer.order.submit', ['tableToken' => $table->qr_token]);

        $first = $this->postJson($url, $payload)->assertCreated();
        $this->postJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('reference', $first->json('reference'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_table_qr_order_is_visible_on_kitchen_pos_and_admin_billing_data(): void
    {
        $table = $this->createEnabledTable();
        $variant = MenuItemVariant::query()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Large',
            'price' => 145,
            'is_active' => true,
        ]);
        $group = MenuModifierGroup::query()->create([
            'menu_item_id' => $this->menuItem->id,
            'name' => 'Add-ons',
            'is_required' => false,
            'sort_order' => 1,
        ]);
        $modifier = MenuModifierOption::query()->create([
            'menu_modifier_group_id' => $group->id,
            'name' => 'Extra cheese',
            'price_delta' => 15,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $orderResponse = $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'variant_id' => $variant->id,
                'modifier_option_ids' => [$modifier->id],
                'quantity' => 1,
            ]],
        ])->assertCreated();
        $reference = $orderResponse->json('reference');

        $kitchen = User::query()->create([
            'name' => 'Kitchen Staff',
            'email' => 'table-order-kitchen@example.test',
            'password' => 'password',
            'role' => 'kitchen',
        ]);
        $this->actingAs($kitchen)
            ->get(route('staff.kitchen.board'))
            ->assertOk()
            ->assertSee('Table 12 - Order 1')
            ->assertSee($reference)
            ->assertSee('Database meal')
            ->assertSee('Large')
            ->assertSee('Extra cheese');

        $cashier = User::query()->create([
            'name' => 'Cashier',
            'email' => 'table-order-cashier@example.test',
            'password' => 'password',
            'role' => 'cashier',
        ]);
        $this->postJson(route('customer.order.bill-request', ['tableToken' => $table->qr_token]), [
            'payment_method' => 'cash',
        ])->assertOk();
        $session = TableSession::query()->where('table_id', $table->id)->firstOrFail();
        $this->actingAs($cashier)
            ->getJson(route('staff.pos.bill-requests'))
            ->assertOk()
            ->assertJsonPath('requests.0.table_number', '12')
            ->assertJsonPath('requests.0.payment_method', 'cash')
            ->assertJsonPath('requests.0.total', 160)
            ->assertJsonPath('requests.0.bill_url', route('staff.pos.table-session-bill', $session));
        $this->get(route('staff.pos.index'))
            ->assertOk()
            ->assertSee('TABLE 12 - READY FOR BILL OUT')
            ->assertSee('Cash')
            ->assertSee('Record terminal payment')
            ->assertSee('data-bill-request-count', false);
        $this->get(route('staff.pos.table-session-bill', $session))
            ->assertOk()
            ->assertSee('Table 12 - Order 1')
            ->assertSee('already paid on a separate terminal')
            ->assertSee($reference)
            ->assertSee('Database meal')
            ->assertSee('Large')
            ->assertSee('Extra cheese');
        $this->get(route('staff.pos.table-qr'))
            ->assertOk()
            ->assertSee('Bill requested')
            ->assertSee('Table 12');

        $admin = User::query()->create([
            'name' => 'Administrator',
            'email' => 'table-order-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $this->actingAs($admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('tables.0.table_status', 'bill_requested')
            ->assertJsonPath('tables.0.table_status_label', 'Bill requested')
            ->assertJsonPath('orders.0.source', 'Table QR')
            ->assertJsonPath('orders.0.tableNo', '12')
            ->assertJsonPath('orders.0.paymentStatus', 'unpaid')
            ->assertJsonPath('orders.0.revenue', false);
        $this->get(route('admin.notifications'))
            ->assertOk()
            ->assertSee('Ready for bill out')
            ->assertSee('TABLE 12 requested a bill by Cash');
    }

    public function test_cashier_settles_all_batches_through_their_shared_table_session(): void
    {
        $table = $this->createEnabledTable();
        $submitUrl = route('customer.order.submit', ['tableToken' => $table->qr_token]);
        $orders = [];
        foreach (range(1, 2) as $index) {
            $orders[] = $this->postJson($submitUrl, [
                'idempotency_key' => (string) Str::uuid(),
                'items' => [[
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                ]],
            ])->assertCreated()->json('reference');
        }
        $session = TableSession::query()->where('table_id', $table->id)->firstOrFail();
        $this->postJson(route('customer.order.bill-request', ['tableToken' => $table->qr_token]), [
            'payment_method' => 'gcash',
        ])->assertOk()->assertJsonPath('total', 200);

        $cashier = User::query()->create([
            'name' => 'Cashier',
            'email' => 'table-bill-cashier@example.test',
            'password' => 'password',
            'role' => 'cashier',
        ]);
        $this->actingAs($cashier)
            ->get(route('staff.pos.index'))
            ->assertOk()
            ->assertSee('TABLE 12 - READY FOR BILL OUT')
            ->assertSee('GCash')
            ->assertSee('Record terminal payment')
            ->assertSee('TABLE 12')
            ->assertDontSee('Unpaid Table QR bills');
        $this->get(route('staff.pos.table-session-bill', $session))
            ->assertOk()
            ->assertSee($orders[0])
            ->assertSee($orders[1]);

        $this->post(route('staff.pos.table-session-paid', $session), [
            'payment_method' => 'cash',
            'amount_tendered' => 200,
        ])->assertRedirect(route('staff.pos.index'));

        $paidOrders = Order::query()->whereIn('reference_number', $orders)->get();
        $this->assertCount(2, $paidOrders);
        $this->assertTrue($paidOrders->every(fn (Order $order): bool => $order->payment_status === 'paid'));
        $this->assertSame(200.0, (float) $paidOrders->sum('total_amount'));
        $this->assertSame(200.0, (float) $paidOrders->sum('amount_tendered'));
        $this->assertSame(21.43, (float) $paidOrders->sum('vat_amount'));
        $this->assertSame(0.0, (float) $paidOrders->sum('discount_amount'));
        $this->assertSame('closed', $session->fresh()->status);
        $this->assertSame(200.0, (float) TableSessionPayment::query()->firstOrFail()->total);
    }

    public function test_separate_customer_sessions_see_the_same_table_bill_and_order_batches(): void
    {
        $table = $this->createEnabledTable();
        $submitUrl = route('customer.order.submit', ['tableToken' => $table->qr_token]);

        $firstOrder = $this->withSession(['device' => 'phone-one'])
            ->postJson($submitUrl, [
                'idempotency_key' => (string) Str::uuid(),
                'items' => [[
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                ]],
            ])->assertCreated();

        $this->withSession(['device' => 'phone-two'])
            ->getJson(route('customer.order.session', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertJsonPath('session.order_count', 1)
            ->assertJsonPath('session.orders.0.reference', $firstOrder->json('reference'))
            ->assertJsonPath('session.total', 100);

        TableSession::query()->firstOrFail()->update([
            'expires_at' => now()->subHours(5),
        ]);
        $this->withSession(['device' => 'phone-two'])
            ->postJson($submitUrl, [
                'idempotency_key' => (string) Str::uuid(),
                'items' => [[
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                ]],
            ])->assertCreated()
            ->assertJsonPath('batch_number', 2)
            ->assertJsonPath('session.order_count', 2)
            ->assertJsonPath('session.total', 200);

        $this->withSession(['device' => 'phone-one'])
            ->getJson(route('customer.order.session', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertJsonPath('session.order_count', 2)
            ->assertJsonPath('session.total', 200);

        $this->assertDatabaseCount('table_sessions', 1);
    }

    public function test_bill_total_excludes_cancelled_orders_and_cancelled_items(): void
    {
        $table = $this->createEnabledTable();
        $references = [];
        foreach (range(1, 3) as $index) {
            $references[] = $this->postJson(route('customer.order.submit', ['tableToken' => $table->qr_token]), [
                'idempotency_key' => (string) Str::uuid(),
                'items' => [[
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                ]],
            ])->assertCreated()->json('reference');
        }

        $orders = Order::query()->whereIn('reference_number', $references)->orderBy('batch_number')->get();
        $orders[0]->update(['status' => 'cancelled']);
        $orders[1]->items()->firstOrFail()->update(['cancelled_at' => now()]);

        $this->postJson(route('customer.order.bill-request', ['tableToken' => $table->qr_token]), [
            'payment_method' => 'card',
        ])->assertOk()
            ->assertJsonPath('session.order_count', 2)
            ->assertJsonPath('session.orders.0.total', 0)
            ->assertJsonPath('session.orders.1.total', 0)
            ->assertJsonPath('session.orders.2.total', 100)
            ->assertJsonPath('total', 100);
    }

    public function test_bill_request_validation_is_idempotent_and_cancellation_reopens_ordering(): void
    {
        $table = $this->createEnabledTable();
        $submitUrl = route('customer.order.submit', ['tableToken' => $table->qr_token]);
        $billRequestUrl = route('customer.order.bill-request', ['tableToken' => $table->qr_token]);

        $this->postJson($submitUrl, [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();
        $session = TableSession::query()->where('table_id', $table->id)->firstOrFail();

        $this->postJson($billRequestUrl, ['payment_method' => 'bitcoin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
        $this->assertSame('open', $session->fresh()->status);
        $this->assertNull($session->fresh()->bill_requested_at);

        $this->postJson($billRequestUrl, [
            'payment_method' => 'gcash',
            'total' => 0.01,
            'orders' => [],
        ])->assertOk()
            ->assertJsonPath('total', 100)
            ->assertJsonPath('payment_method', 'gcash');

        $this->postJson($billRequestUrl, [
            'payment_method' => 'cash',
            'total' => 999999,
            'orders' => [],
        ])->assertOk()
            ->assertJsonPath('total', 100)
            ->assertJsonPath('payment_method', 'gcash')
            ->assertJsonPath('message', 'The bill has already been requested. The cashier is on the way.');
        $this->assertSame(1, DB::table('activity_logs')
            ->where('action', 'Customer requested table bill')
            ->where('subject_id', $session->id)
            ->count());

        $this->postJson($submitUrl, [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ]],
        ])->assertStatus(409)
            ->assertJsonPath('message', 'A bill has already been requested for this table. Cancel the request before ordering again.');

        $this->postJson(route('customer.order.bill-request.cancel', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertJsonPath('session.status', 'open')
            ->assertJsonPath('session.requested_payment_method', null);
        $this->assertNull($session->fresh()->bill_requested_at);

        $this->postJson($submitUrl, [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ]],
        ])->assertCreated()
            ->assertJsonPath('batch_number', 2)
            ->assertJsonPath('session.order_count', 2)
            ->assertJsonPath('session.total', 200);
    }

    private function createEnabledTable(): RestaurantTable
    {
        return RestaurantTable::query()->create([
            'table_number' => '12',
            'name' => 'Table 12',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);
    }
}
