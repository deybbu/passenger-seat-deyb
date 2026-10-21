<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRegisterAndQrTest extends TestCase
{
    private User $admin;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'admin_operations_test',
            'database.connections.admin_operations_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('admin_operations_test');
        DB::setDefaultConnection('admin_operations_test');
        $this->artisan('migrate:fresh', ['--database' => 'admin_operations_test'])
            ->assertExitCode(0);
        $this->withoutVite();

        $this->admin = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $category = MenuCategory::query()->create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
        ]);
        $this->menuItem = MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Test meal',
            'slug' => 'test-meal',
            'price' => 120,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    public function test_admin_register_holds_resumes_and_records_payment_from_database_prices(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.pos'))
            ->assertOk()
            ->assertSee('id="view"', false);

        $held = $this->postJson(route('admin.pos.hold'), [
            'type' => 'dine_in',
            'table_number' => '',
            'customer_name' => 'Test Guest',
            'items' => [$this->menuItem->id => 2],
            'payment_method' => 'cash',
        ])->assertOk()->json('order');

        $heldOrder = Order::query()->findOrFail($held['id']);
        $this->assertNotNull($heldOrder->held_at);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $heldOrder->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ]);
        $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonMissing(['no' => $heldOrder->reference_number]);

        $this->postJson(route('admin.pos.held.resume', $heldOrder))
            ->assertOk()
            ->assertJsonPath('items.'.$this->menuItem->id, 2)
            ->assertJsonPath('customer', 'Test Guest');

        $this->actingAs($this->admin)
            ->post(route('admin.pos.store'), [
                'held_order_id' => $heldOrder->id,
                'type' => 'take_out',
                'items' => [$this->menuItem->id => 2],
                'payment_method' => 'cash',
                'amount_tendered' => 240,
                'subtotal' => 1,
                'total_amount' => 1,
            ])
            ->assertRedirect(route('admin.pos'))
            ->assertSessionHasNoErrors();

        $paidOrder = Order::query()->where('payment_status', 'paid')->firstOrFail();
        $this->assertSame(240.0, (float) $paidOrder->subtotal);
        $this->assertSame(240.0, (float) $paidOrder->total_amount);
        $this->assertSame(240.0, (float) $paidOrder->amount_tendered);
        $this->assertNull($paidOrder->held_at);
        $this->assertDatabaseMissing('orders', ['id' => $heldOrder->id]);
    }

    public function test_admin_register_rejects_underpayment_and_non_admin_access(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.pos.store'), [
                'type' => 'take_out',
                'items' => [$this->menuItem->id => 1],
                'payment_method' => 'cash',
                'amount_tendered' => 119.99,
            ])
            ->assertSessionHasErrors('amount_tendered');

        $this->assertDatabaseCount('orders', 0);

        $cashier = User::query()->create([
            'name' => 'Cashier',
            'email' => 'cashier@example.test',
            'password' => 'password',
            'role' => 'cashier',
        ]);
        $this->actingAs($cashier)->get(route('admin.pos'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.billing'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.table-qr'))->assertForbidden();
    }

    public function test_qr_billing_shows_real_table_orders_and_disabled_qr_cannot_place_orders(): void
    {
        $table = RestaurantTable::query()->create([
            'table_number' => '5',
            'name' => 'Table 5',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);
        $session = TableSession::query()->create([
            'table_number' => '5',
            'public_token' => Str::random(64),
            'active_table_number' => '5',
            'guest_count' => 2,
            'opened_by' => $this->admin->id,
            'opened_at' => now(),
            'expires_at' => now()->addHours(3),
        ]);
        $placed = $this->postJson(route('customer.qr.place'), [
            'table_token' => $session->public_token,
            'submission_token' => (string) Str::uuid(),
            'items' => [$this->menuItem->id => 1],
        ])->assertCreated();

        $this->actingAs($this->admin)
            ->get(route('admin.billing'))
            ->assertOk()
            ->assertSee('id="view"', false);

        $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonFragment([
                'no' => $placed->json('reference'),
                'tableNo' => '5',
            ]);

        $this->actingAs($this->admin)
            ->get(route('admin.table-qr'))
            ->assertOk()
            ->assertSee('id="view"', false);

        $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonFragment(['table_number' => '5']);

        $this->actingAs($this->admin)
            ->patch(route('admin.table-qr.toggle', $table))
            ->assertRedirect();

        $this->get(route('customer.qr.show', ['table' => '5']))
            ->assertOk()
            ->assertSee('QR ordering is disabled for this table');

        $this->postJson(route('customer.qr.place'), [
            'table_token' => $session->public_token,
            'submission_token' => (string) Str::uuid(),
            'items' => [$this->menuItem->id => 1],
        ])->assertStatus(409);

        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'is_enabled' => false,
        ]);
    }
}
