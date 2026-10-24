<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\InventoryStockLot;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryOfflineSyncTest extends TestCase
{
    private User $staff;

    private Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh')->assertExitCode(0);

        $this->staff = User::query()->create([
            'name' => 'Inventory Staff',
            'email' => 'inventory-sync@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);
        $this->ingredient = Ingredient::query()->create([
            'name' => 'Rice',
            'current_stock' => 10,
            'unit' => 'kg',
            'unit_cost' => 50,
            'min_threshold' => 1,
        ]);
        InventoryStockLot::query()->create([
            'ingredient_id' => $this->ingredient->id,
            'received_quantity' => 10,
            'remaining_quantity' => 10,
            'unit_cost' => 50,
            'receipt_token' => (string) Str::uuid(),
            'received_at' => now()->subDay(),
        ]);
    }

    public function test_offline_waste_retry_is_recorded_once_and_replayed(): void
    {
        $key = (string) Str::uuid();
        $event = [
            'idempotency_key' => $key,
            'operation' => 'waste.store',
            'staff_id' => $this->staff->id,
            'payload' => [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 1,
                'unit' => 'kg',
                'stage' => 'Preparation / trimming',
                'reason' => 'Dropped / spilled',
                'storage_zone' => 'Restaurant',
            ],
        ];

        $this->actingAs($this->staff)
            ->postJson(route('staff.inventory.sync'), ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('results.0.status_code', 201)
            ->assertJsonPath('results.0.replayed', false);

        $this->actingAs($this->staff)
            ->postJson(route('staff.inventory.sync'), ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('results.0.status_code', 201)
            ->assertJsonPath('results.0.replayed', true);

        $this->assertSame(9.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(1, InventoryLog::query()->where('type', 'waste')->count());
        $this->assertDatabaseHas('inventory_idempotency_keys', [
            'idempotency_key' => $key,
            'user_id' => $this->staff->id,
            'operation' => 'waste.store',
            'status' => 'completed',
        ]);
    }

    public function test_offline_event_cannot_be_synced_by_a_different_staff_member(): void
    {
        $otherStaff = User::query()->create([
            'name' => 'Other Staff',
            'email' => 'other-inventory-sync@example.test',
            'password' => 'password',
            'role' => 'inventory',
        ]);

        $this->actingAs($otherStaff)
            ->postJson(route('staff.inventory.sync'), ['events' => [[
                'idempotency_key' => (string) Str::uuid(),
                'operation' => 'waste.store',
                'staff_id' => $this->staff->id,
                'payload' => [
                    'ingredient_id' => $this->ingredient->id,
                    'quantity' => 1,
                    'unit' => 'kg',
                    'stage' => 'Preparation / trimming',
                    'reason' => 'Dropped / spilled',
                ],
            ]]])
            ->assertOk()
            ->assertJsonPath('results.0.status_code', 403);

        $this->assertSame(10.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(0, InventoryLog::query()->where('type', 'waste')->count());
    }

    public function test_reusing_an_idempotency_key_for_different_payload_is_rejected(): void
    {
        $key = (string) Str::uuid();
        $event = [
            'idempotency_key' => $key,
            'operation' => 'waste.store',
            'staff_id' => $this->staff->id,
            'payload' => [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 1,
                'unit' => 'kg',
                'stage' => 'Preparation / trimming',
                'reason' => 'Dropped / spilled',
            ],
        ];
        $this->actingAs($this->staff)->postJson(route('staff.inventory.sync'), ['events' => [$event]])->assertOk();
        $event['payload']['quantity'] = 2;

        $this->actingAs($this->staff)
            ->postJson(route('staff.inventory.sync'), ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('results.0.status_code', 409);

        $this->assertSame(9.0, (float) $this->ingredient->fresh()->current_stock);
        $this->assertSame(1, InventoryLog::query()->where('type', 'waste')->count());
    }
}
