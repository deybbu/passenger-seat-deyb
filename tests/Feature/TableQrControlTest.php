<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TableQrControlTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'table_qr_control_test',
            'database.connections.table_qr_control_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'app.url' => 'https://orders.passengerseat.example.com',
        ]);
        DB::purge('table_qr_control_test');
        DB::setDefaultConnection('table_qr_control_test');
        $this->artisan('migrate:fresh', ['--database' => 'table_qr_control_test'])
            ->assertExitCode(0);
        $this->withoutVite();

        $this->admin = User::query()->create([
            'name' => 'QR Admin',
            'email' => 'qr-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_manage_tables_rotate_tokens_and_print_real_qr_codes(): void
    {
        $created = $this->actingAs($this->admin)
            ->postJson(route('admin.api.reference.table-qr.store'), [
                'table_number' => '21',
                'name' => 'Window Table',
            ])
            ->assertCreated()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('table_status', 'free');
        $table = RestaurantTable::query()->findOrFail($created->json('id'));
        $oldToken = $table->qr_token;
        $oldUrl = 'https://orders.passengerseat.example.com/order/'.$oldToken;

        $this->assertSame($oldUrl, $created->json('qr_url'));
        $this->assertStringNotContainsString('localhost', $created->json('qr_url'));
        $this->assertStringNotContainsString('127.0.0.1', $created->json('qr_url'));

        $this->patchJson(route('admin.api.reference.table-qr.update', ['restaurantTable' => $table]), [
            'table_number' => '21A',
            'name' => 'Window Booth',
        ])->assertOk()
            ->assertJsonPath('table_number', '21A')
            ->assertJsonPath('name', 'Window Booth');

        $this->patchJson(route('admin.api.reference.table-qr.toggle', ['restaurantTable' => $table]))
            ->assertOk()
            ->assertJsonPath('enabled', true);

        TableSession::query()->create([
            'table_number' => '21A',
            'table_id' => $table->id,
            'public_token' => Str::random(64),
            'active_table_number' => '21A',
            'opened_at' => now(),
        ]);
        $this->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('tables.0.table_status', 'open')
            ->assertJsonPath('tables.0.table_status_label', 'Open tab');

        TableSession::query()->where('table_id', $table->id)->update([
            'status' => 'bill_requested',
            'bill_requested_at' => now(),
        ]);
        $this->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('tables.0.table_status', 'bill_requested')
            ->assertJsonPath('tables.0.table_status_label', 'Bill requested');
        $this->get(route('admin.table-qr'))
            ->assertOk()
            ->assertSee('table-session-status.js');

        $this->get(route('customer.order.show', ['tableToken' => $oldToken]))
            ->assertOk()
            ->assertSee('Table 21A');

        $rotated = $this->postJson(route('admin.api.reference.table-qr.regenerate', [
            'restaurantTable' => $table,
        ]))->assertOk();
        $newToken = $rotated->json('qr_url');
        $this->assertNotSame($oldToken, $table->fresh()->qr_token);
        $this->assertSame(
            'https://orders.passengerseat.example.com/order/'.$table->fresh()->qr_token,
            $newToken,
        );
        $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('tables.0.qr_url', $newToken);

        $this->get(route('customer.order.show', ['tableToken' => $oldToken]))
            ->assertOk()
            ->assertSee("This QR code isn't valid", false);
        $this->get(route('customer.order.show', ['tableToken' => $table->fresh()->qr_token]))
            ->assertOk()
            ->assertSee('Table 21A');

        $imageUrl = route('admin.api.reference.table-qr.image', ['restaurantTable' => $table]);
        $this->get($imageUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertSee('<svg', false);
        $this->get($imageUrl.'?download=1')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="table-21a-qr.svg"');

        $this->actingAs($this->admin)
            ->get(route('admin.table-qr.print'))
            ->assertOk()
            ->assertSee('Table 21A')
            ->assertSee('Print sheet')
            ->assertSee('<h2>Table 21A</h2>', false)
            ->assertSee(route('admin.api.reference.table-qr.image', [
                'restaurantTable' => $table->id,
            ], false), false);

        $this->patchJson(route('admin.api.reference.table-qr.toggle', ['restaurantTable' => $table]))
            ->assertOk()
            ->assertJsonPath('enabled', false);
        $this->get(route('customer.order.show', ['tableToken' => $table->fresh()->qr_token]))
            ->assertOk()
            ->assertSee("This QR code isn't valid", false);
        $this->deleteJson(route('admin.api.reference.table-qr.delete', ['restaurantTable' => $table]))
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertDatabaseMissing('restaurant_tables', ['id' => $table->id]);
    }

    public function test_localhost_app_url_is_blocked_from_qr_links_and_images(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);
        $table = RestaurantTable::query()->create([
            'table_number' => '5',
            'name' => 'Table 5',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('tables.0.qr_url', null);

        $this->actingAs($this->admin)
            ->get(route('admin.api.reference.table-qr.image', ['restaurantTable' => $table]))
            ->assertStatus(409)
            ->assertSee('Do not use localhost or 127.0.0.1.');

        $this->actingAs($this->admin)
            ->get(route('admin.table-qr.print'))
            ->assertOk()
            ->assertSee('Do not use localhost or 127.0.0.1.')
            ->assertSee('QR unavailable');
    }

    public function test_table_model_generates_unique_tokens_and_links_sessions(): void
    {
        $first = RestaurantTable::query()->create([
            'table_number' => '31',
            'name' => 'Table 31',
            'is_enabled' => false,
        ]);
        $second = RestaurantTable::query()->create([
            'table_number' => '32',
            'name' => 'Table 32',
            'is_enabled' => false,
        ]);

        $this->assertNotEmpty($first->qr_token);
        $this->assertNotSame($first->qr_token, $second->qr_token);

        $session = TableSession::query()->create([
            'table_number' => '31',
            'table_id' => $first->id,
            'public_token' => Str::random(64),
            'active_table_number' => '31',
            'opened_at' => now(),
        ]);

        $this->assertTrue($first->tableSessions->contains($session));
    }

    public function test_private_lan_app_url_generates_same_wifi_qr_links_and_images(): void
    {
        config(['app.url' => 'http://192.168.1.5:8000']);
        $table = RestaurantTable::query()->create([
            'table_number' => '6',
            'name' => 'Table 6',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->assertJsonPath('tables.0.qr_url', 'http://192.168.1.5:8000/order/'.$table->qr_token);

        $this->actingAs($this->admin)
            ->get(route('admin.api.reference.table-qr.image', ['restaurantTable' => $table]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
    }
}
