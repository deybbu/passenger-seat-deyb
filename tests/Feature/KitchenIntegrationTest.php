<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\InventoryStockLot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AddsOrderRecipe;
use Tests\TestCase;

class KitchenIntegrationTest extends TestCase
{
    use AddsOrderRecipe;

    private MenuItem $menuItem;

    private Ingredient $ingredient;

    private User $cashier;

    private User $kitchen;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Manila',
            'database.default' => 'kitchen_integration_test',
            'database.connections.kitchen_integration_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('kitchen_integration_test');
        DB::setDefaultConnection('kitchen_integration_test');
        $this->artisan('migrate:fresh', ['--database' => 'kitchen_integration_test'])->assertExitCode(0);
        $this->withoutVite();

        $category = MenuCategory::query()->create(['name' => 'Mains', 'slug' => 'mains', 'is_active' => true]);
        $this->menuItem = MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Kitchen test meal',
            'slug' => 'kitchen-test-meal',
            'price' => 100,
            'is_active' => true,
            'is_available' => true,
        ]);
        $this->ingredient = $this->addOrderRecipe($this->menuItem, 'Kitchen test stock');
        $this->cashier = $this->makeUser('Kitchen Test Cashier', 'kitchen-cashier@example.test', 'cashier');
        $this->kitchen = $this->makeUser('Kitchen Test Staff', 'kitchen-staff@example.test', 'kitchen');
        $this->table = RestaurantTable::query()->create([
            'table_number' => '7',
            'name' => 'Table 7',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);
    }

    public function test_qr_cashier_and_website_orders_share_stock_and_kitchen_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 12:30:00', 'Asia/Manila'));
        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['menu_item_id' => $this->menuItem->id, 'quantity' => 1]],
            'special_instructions' => 'No onions',
        ];
        $qrUrl = route('customer.order.submit', ['tableToken' => $this->table->qr_token]);
        $qrResponse = $this->postJson($qrUrl, $payload)->assertCreated();
        $qrOrder = Order::query()->where('reference_number', $qrResponse->json('reference'))->firstOrFail();
        $this->assertSame('table_qr', $qrOrder->source);
        $this->assertSame('pending', $qrOrder->status);
        $this->assertSame('7', $qrOrder->table_number);
        $this->assertSame(499.99, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(1, InventoryLog::query()->where('order_id', $qrOrder->id)->where('type', 'stock_out')->count());
        $this->postJson($qrUrl, $payload)->assertOk();
        $this->assertSame(499.99, (float) $this->ingredient->fresh()->current_stock);

        $cashierResponse = $this->actingAs($this->cashier)->postJson(route('staff.pos.orders.store'), [
            'type' => 'dine_in',
            'handling' => 'pay_later',
            'table_number' => '7',
            'customer_name' => 'Cashier guest',
            'items' => [['item_id' => $this->menuItem->id, 'quantity' => 1]],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $cashierOrder = Order::query()->findOrFail($cashierResponse->json('id'));
        $this->assertSame('cashier', $cashierOrder->source);
        $this->assertSame($this->cashier->id, $cashierOrder->cashier_id);
        $this->assertSame(2, $cashierOrder->batch_number);

        $websiteResponse = $this->withSession(['cart' => [(string) $this->menuItem->id => 1]])
            ->post(route('orders.store'), ['type' => 'dine_in', 'table_number' => '7'])
            ->assertRedirect();
        $websiteOrder = Order::query()->where('source', 'website')->firstOrFail();
        $websiteResponse->assertRedirect(route('orders.thank-you', $websiteOrder->reference_number));
        $this->assertSame('pending', $websiteOrder->status);
        $this->assertSame('7', $websiteOrder->table_number);
        $this->assertSame(3, $websiteOrder->batch_number);
        $this->assertSame(499.97, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(1, InventoryLog::query()->where('order_id', $websiteOrder->id)->where('type', 'stock_out')->count());

        $this->actingAs($this->kitchen)
            ->patchJson(route('staff.kitchen.orders.status', $qrOrder), ['status' => 'ready'])
            ->assertUnprocessable();
        $this->patchJson(route('staff.kitchen.orders.status', $qrOrder), ['status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('order.st', 'Preparing');
        $this->patchJson(route('staff.kitchen.orders.status', $qrOrder), ['status' => 'preparing'])
            ->assertUnprocessable();
        $this->patchJson(route('staff.kitchen.orders.status', $qrOrder), ['status' => 'ready'])
            ->assertOk();
        $served = $this->patchJson(route('staff.kitchen.orders.status', $qrOrder), ['status' => 'served'])
            ->assertOk()
            ->assertJsonPath('order.st', 'Served');
        $this->assertGreaterThan(0, $served->json('server_now'));
        $this->assertSame('unpaid', $qrOrder->fresh()->payment_status);
        $this->assertSame('open', $qrOrder->tableSession->fresh()->status);
        $this->assertDatabaseMissing('payments', ['order_id' => $qrOrder->id]);
        $this->getJson(route('customer.qr.orders', ['tableSession' => $qrOrder->tableSession->public_token]))
            ->assertOk()
            ->assertJsonPath('orders.0.status', 'served');

        $this->postJson(route('customer.order.bill-request', ['tableToken' => $this->table->qr_token]), [
            'payment_method' => 'cash',
        ])->assertOk();
        $this->actingAs($this->cashier)
            ->get(route('staff.pos.table-session-bill', $qrOrder->tableSession))
            ->assertOk()
            ->assertSee('Table 7 - Order 1')
            ->assertSee('Served');
        $this->assertSame('bill_requested', $qrOrder->tableSession->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_missing_recipe_and_insufficient_stock_are_rejected_and_availability_is_shared(): void
    {
        $unconfigured = MenuItem::query()->create([
            'name' => 'No recipe drink',
            'slug' => 'no-recipe-drink',
            'price' => 50,
            'is_active' => true,
            'is_available' => true,
        ]);
        $short = MenuItem::query()->create([
            'name' => 'Short stock dish',
            'slug' => 'short-stock-dish',
            'price' => 75,
            'is_active' => true,
            'is_available' => true,
        ]);
        $shortIngredient = Ingredient::query()->create([
            'name' => 'Nearly empty flour',
            'current_stock' => 0.005,
            'unit' => 'kg',
            'unit_cost' => 20,
            'min_threshold' => 0.1,
        ]);
        InventoryStockLot::query()->create([
            'ingredient_id' => $shortIngredient->id,
            'received_quantity' => 0.005,
            'remaining_quantity' => 0.005,
            'unit_cost' => 20,
            'receipt_token' => (string) Str::uuid(),
            'received_at' => now(),
        ]);
        $short->ingredients()->attach($shortIngredient->id, ['quantity' => 0.01]);
        $this->menuItem->ingredients()->updateExistingPivot($this->ingredient->id, ['quantity' => 600]);

        foreach ([$unconfigured, $short] as $item) {
            $this->postJson(route('customer.order.submit', ['tableToken' => $this->table->qr_token]), [
                'idempotency_key' => (string) Str::uuid(),
                'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
            ])->assertStatus(409);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(500.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(0, InventoryLog::query()->count());

        $this->get(route('menu'))
            ->assertOk()
            ->assertSee('No recipe drink')
            ->assertSee('Short stock dish')
            ->assertSee('Sold out');
        $session = TableSession::query()->create([
            'table_number' => '7',
            'table_id' => $this->table->id,
            'public_token' => Str::random(64),
            'active_table_number' => '7',
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->getJson(route('customer.order.session', ['tableToken' => $this->table->qr_token]))
            ->assertOk()
            ->assertJsonPath('available_item_ids', []);
        $this->actingAs($this->cashier)
            ->getJson(route('staff.pos.data'))
            ->assertOk()
            ->assertJsonPath('menu.1.4', 0);
        $this->actingAs($this->kitchen)
            ->getJson(route('staff.kitchen.inventory.data'))
            ->assertOk()
            ->assertJsonPath('availability.'.$short->id.'.available', false)
            ->assertJsonPath('availability.'.$unconfigured->id.'.missing.0', 'Recipe not configured');
    }

    public function test_waste_validation_updates_stock_log_kitchen_data_and_admin_analytics(): void
    {
        $lot = $this->ingredient->stockLots()->firstOrFail();
        $lot->update(['expires_at' => today()->addDay()]);

        $url = route('staff.kitchen.waste.store');
        $this->actingAs($this->kitchen)
            ->postJson($url, [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 500.01,
                'unit' => 'kg',
                'stage' => 'Preparation / trimming',
                'reason' => 'Dropped / spilled',
            ])
            ->assertUnprocessable();
        $this->assertSame(500.0, (float) $this->ingredient->fresh()->current_stock);

        $this->postJson($url, [
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 0.5,
            'unit' => 'kg',
            'stage' => 'Preparation / trimming',
            'reason' => 'Dropped / spilled',
        ])->assertCreated()->assertJsonPath('cost', 5);

        $this->assertSame(499.5, (float) $this->ingredient->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $this->ingredient->id,
            'user_id' => $this->kitchen->id,
            'type' => 'waste',
            'quantity' => -0.5,
            'reason' => 'Dropped / spilled',
            'stage' => 'Preparation / trimming',
            'cost_amount' => 5,
        ]);
        $this->getJson(route('staff.kitchen.waste.data', ['range' => 'today']))
            ->assertOk()
            ->assertJsonPath('entries.0.cost', 5)
            ->assertJsonPath('near_expiry.0.lot', $lot->id);

        $admin = $this->makeUser('Kitchen Analytics Admin', 'kitchen-analytics-admin@example.test', 'admin');
        $this->actingAs($admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('waste.0.v', 5)
            ->assertJsonPath('waste.0.stage', 'Preparation / trimming');
    }

    private function makeUser(string $name, string $email, string $role): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
        ]);
    }
}
