<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\TableSession;
use App\Models\TableSessionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TableSessionPaymentTest extends TestCase
{
    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'table_session_payment_test',
            'database.connections.table_session_payment_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('table_session_payment_test');
        DB::setDefaultConnection('table_session_payment_test');
        $this->artisan('migrate:fresh', ['--database' => 'table_session_payment_test'])
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
            'price' => 100,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    public function test_cashier_settles_a_requested_session_and_manager_reopens_with_an_audited_reversal(): void
    {
        $table = RestaurantTable::query()->create([
            'table_number' => '12',
            'name' => 'Table 12',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);
        $submitUrl = route('customer.order.submit', ['tableToken' => $table->qr_token]);
        $references = [];
        foreach (range(1, 2) as $index) {
            $references[] = $this->postJson($submitUrl, [
                'idempotency_key' => (string) Str::uuid(),
                'items' => [[
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                ]],
            ])->assertCreated()->json('reference');
        }

        $session = TableSession::query()->firstOrFail();
        $this->assertSame(2, $session->orders()->count());
        $this->assertSame([1, 2], $session->orders()->orderBy('batch_number')->pluck('batch_number')->all());

        $this->postJson(route('customer.order.bill-request', ['tableToken' => $table->qr_token]), [
            'payment_method' => 'gcash',
            'total' => 0.01,
            'orders' => [],
        ])->assertOk()
            ->assertJsonPath('total', 200)
            ->assertJsonPath('payment_method', 'gcash');

        $cashierRole = Role::query()->where('key', 'cashier')->firstOrFail();
        $cashier = User::query()->create([
            'name' => 'Cashier',
            'email' => 'table-session-cashier@example.test',
            'password' => 'password',
            'role' => 'cashier',
            'role_id' => $cashierRole->id,
        ]);

        $this->actingAs($cashier)
            ->get(route('staff.pos.table-session-bill', $session))
            ->assertOk()
            ->assertSee('Table 12 - Order 1')
            ->assertSee('Table 12 - Order 2')
            ->assertSee('GCash')
            ->assertSee('<option value="gcash" selected>GCash</option>', false)
            ->assertSee('Kitchen warning');

        $this->post(route('staff.pos.paid', $session->orders()->first()), [
            'payment_method' => 'cash',
            'amount_tendered' => 200,
        ])->assertStatus(409);

        $this->post(route('staff.pos.table-session-paid', $session), [
            'payment_method' => 'cash',
            'discount_type' => 'SC',
            'discount_cardholder_name' => 'Test Senior',
            'discount_id_number' => 'SC-1234',
            'amount_tendered' => 200,
        ])->assertRedirect(route('staff.pos.index'));

        $payment = TableSessionPayment::query()->where('type', 'payment')->firstOrFail();
        $this->assertSame(200.0, (float) $payment->subtotal);
        $this->assertSame(21.43, (float) $payment->vat_exemption);
        $this->assertSame(35.71, (float) $payment->discount);
        $this->assertSame(0.0, (float) $payment->vat);
        $this->assertSame(142.86, (float) $payment->total);
        $this->assertSame(57.14, (float) $payment->change_given);
        $this->assertSame('closed', $session->fresh()->status);
        $this->assertNull($session->fresh()->active_table_number);
        $this->assertSame('cash', $session->fresh()->paid_payment_method);
        $this->assertSame($payment->payment_reference, TableSessionPayment::query()->firstOrFail()->payment_reference);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => TableSession::class,
            'subject_id' => $session->id,
            'action' => 'Table session payment recorded',
        ]);
        $this->assertTrue(Order::query()->whereIn('reference_number', $references)
            ->get()->every(fn (Order $order): bool => $order->payment_status === 'paid'));

        $this->post(route('staff.pos.table-session-reopen', $session), [
            'reason' => 'Payment method was recorded incorrectly.',
        ])->assertForbidden();
        $this->assertDatabaseCount('table_session_payments', 1);

        $managerRole = Role::query()->where('key', 'restaurant_manager')->firstOrFail();
        $manager = User::query()->create([
            'name' => 'Restaurant Manager',
            'email' => 'table-session-manager@example.test',
            'password' => 'password',
            'role' => 'admin',
            'role_id' => $managerRole->id,
        ]);
        $this->actingAs($manager)
            ->post(route('staff.pos.table-session-reopen', $session), [
                'reason' => 'Payment method was recorded incorrectly.',
            ])
            ->assertRedirect(route('staff.pos.ledger'));

        $reversal = TableSessionPayment::query()->where('type', 'reversal')->firstOrFail();
        $this->assertSame($payment->id, $reversal->reversal_of_id);
        $this->assertSame($payment->payment_reference, $reversal->reversalOf->payment_reference);
        $this->assertSame('Payment method was recorded incorrectly.', $reversal->notes);
        $this->assertSame('bill_requested', $session->fresh()->status);
        $this->assertSame('12', $session->fresh()->active_table_number);
        $this->assertTrue(Order::query()->whereIn('reference_number', $references)
            ->get()->every(fn (Order $order): bool => $order->payment_status === 'unpaid'));
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => TableSession::class,
            'subject_id' => $session->id,
            'action' => 'Paid table session reopened',
        ]);
    }

    public function test_payment_closes_the_session_and_the_next_scan_starts_a_fresh_one(): void
    {
        $table = RestaurantTable::query()->create([
            'table_number' => '12',
            'name' => 'Table 12',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);
        $submitUrl = route('customer.order.submit', ['tableToken' => $table->qr_token]);
        $firstOrder = $this->postJson($submitUrl, [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();
        $firstSession = TableSession::query()->where('table_id', $table->id)->firstOrFail();

        $this->postJson(route('customer.order.bill-request', ['tableToken' => $table->qr_token]), [
            'payment_method' => 'cash',
        ])->assertOk();

        $cashierRole = Role::query()->where('key', 'cashier')->firstOrFail();
        $cashier = User::query()->create([
            'name' => 'Next Scan Cashier',
            'email' => 'next-scan-cashier@example.test',
            'password' => 'password',
            'role' => 'cashier',
            'role_id' => $cashierRole->id,
        ]);
        $this->actingAs($cashier)
            ->post(route('staff.pos.table-session-paid', $firstSession), [
                'payment_method' => 'cash',
                'amount_tendered' => 100,
            ])->assertRedirect(route('staff.pos.index'));

        $this->assertSame('closed', $firstSession->fresh()->status);
        $this->getJson(route('customer.order.session', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertJsonPath('session', null);

        $secondOrder = $this->postJson($submitUrl, [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ]],
        ])->assertCreated()
            ->assertJsonPath('batch_number', 1);
        $secondSession = TableSession::query()
            ->where('table_id', $table->id)
            ->whereNull('closed_at')
            ->firstOrFail();

        $this->assertNotSame($firstSession->id, $secondSession->id);
        $this->assertNotSame($firstOrder->json('reference'), $secondOrder->json('reference'));
        $this->assertDatabaseCount('table_sessions', 2);
        $this->assertSame(1, $secondSession->orders()->count());
    }
}
