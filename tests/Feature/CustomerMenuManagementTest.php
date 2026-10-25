<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Support\AddsOrderRecipe;

class CustomerMenuManagementTest extends TestCase
{
    use AddsOrderRecipe;

    protected function setUp(): void
    {
        parent::setUp();

        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = ':memory:';

        config([
            'database.connections.customer_menu_test' => $sqlite,
            'database.default' => 'customer_menu_test',
        ]);
        DB::purge('customer_menu_test');

        Artisan::call('migrate:fresh', [
            '--database' => 'customer_menu_test',
            '--force' => true,
        ]);
    }

    public function test_public_menu_only_shows_active_available_items(): void
    {
        $visible = $this->createMenuItem('Visible dish', true, true);
        $this->addOrderRecipe($visible, 'Visible menu stock');
        $this->createMenuItem('Unavailable dish', true, false);
        $this->createMenuItem('Inactive dish', false, true);

        $this->get(route('menu'))
            ->assertOk()
            ->assertSee('Visible dish')
            ->assertDontSee('Unavailable dish')
            ->assertDontSee('Inactive dish');
    }

    public function test_admin_can_permanently_delete_a_menu_item(): void
    {
        $item = $this->createMenuItem('Item to delete', true, false);
        $admin = new User(['name' => 'Administrator', 'email' => 'admin@example.com', 'role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('admin.menu-management.destroy', $item))
            ->assertRedirect();

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_admin_can_save_origin_new_status_and_supported_serving_variants(): void
    {
        $admin = User::query()->create([
            'name' => 'Administrator',
            'email' => 'menu-admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.api.reference.menu.store'), [
            'name' => 'Bacolod Group Meal',
            'origin' => 'Bacolod',
            'price' => 1200,
            'category' => 'Boodle Flight',
            'is_new' => true,
            'is_active' => true,
            'variants' => [
                ['name' => 'Solo', 'price' => 300, 'portion_factor' => 1],
                ['name' => 'Group (2-3 servings)', 'price' => 800, 'portion_factor' => 2.5],
                ['name' => 'Pitcher', 'price' => 500, 'portion_factor' => 1],
            ],
        ])->assertOk();

        $item = MenuItem::query()->findOrFail($response->json('id'));
        $this->assertSame('Bacolod', $item->origin);
        $this->assertSame('New', $item->collection_type);
        $this->assertEqualsCanonicalizing(
            ['Solo', 'Group (2-3 servings)', 'Pitcher'],
            $item->variants()->pluck('name')->all(),
        );

        $ingredient = $this->addOrderRecipe($item, 'Recipe ingredient');
        $dashboardMenuItem = $this->getJson(route('admin.api.reference.dashboard'))
            ->assertOk()
            ->json('menu.0');
        $this->assertSame('Bacolod', $dashboardMenuItem['origin']);
        $this->assertSame([$ingredient->name], $dashboardMenuItem['ingredients']);
    }

    public function test_added_and_edited_menu_items_are_reflected_in_customer_qr_menu(): void
    {
        $admin = User::query()->create([
            'name' => 'Administrator',
            'email' => 'qr-menu-admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $table = RestaurantTable::query()->create([
            'table_number' => '12',
            'name' => 'Table 12',
            'is_enabled' => true,
            'qr_token' => Str::random(64),
        ]);

        $created = $this->actingAs($admin)->postJson(route('admin.api.reference.menu.store'), [
            'name' => 'New QR dish',
            'price' => 250,
            'category' => 'QR Specials',
            'description' => 'Freshly added dish.',
            'is_active' => true,
        ])->assertOk();

        $item = MenuItem::query()->findOrFail($created->json('id'));
        $this->get(route('customer.order.show', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertViewHas('items', fn (array $items): bool => collect($items)->contains(
                fn (array $menuItem): bool => $menuItem['name'] === 'New QR dish'
                    && $menuItem['price'] === 250.0
                    && $menuItem['category'] === 'QR Specials',
            ))
            ->assertViewHas('menu', fn (array $menu): bool => collect($menu)->contains(
                fn (array $category): bool => collect($category[1])->contains(
                    fn (array $item): bool => $item[0] === 'New QR dish'
                        && $item[3] === 'Freshly added dish.',
                ),
            ));

        $this->patchJson(route('admin.api.reference.menu.archive', $item))
            ->assertOk();
        $this->postJson(route('admin.api.reference.menu.store'), [
            'id' => $item->id,
            'name' => 'Updated QR dish',
            'price' => 325,
            'category' => 'Updated Specials',
            'description' => 'Updated dish details.',
            'is_active' => true,
        ])->assertOk();

        $this->assertNull($item->fresh()->archived_at);
        $this->get(route('customer.order.show', ['tableToken' => $table->qr_token]))
            ->assertOk()
            ->assertViewHas('items', fn (array $items): bool => collect($items)->contains(
                fn (array $menuItem): bool => $menuItem['name'] === 'Updated QR dish'
                    && $menuItem['description'] === 'Updated dish details.'
                    && $menuItem['price'] === 325.0
                    && $menuItem['category'] === 'Updated Specials',
            ))
            ->assertViewHas('menu', fn (array $menu): bool => collect($menu)->contains(
                fn (array $category): bool => collect($category[1])->contains(
                    fn (array $item): bool => $item[0] === 'Updated QR dish'
                        && $item[3] === 'Updated dish details.',
                ),
            ));
    }

    public function test_menu_endpoint_rejects_unsupported_or_reversed_group_variants(): void
    {
        $admin = User::query()->create([
            'name' => 'Administrator',
            'email' => 'invalid-menu-admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $payload = [
            'name' => 'Invalid serving item',
            'price' => 100,
            'category' => 'A La Carte',
            'is_active' => true,
            'variants' => [
                ['name' => 'Large', 'price' => 100, 'portion_factor' => 1],
            ],
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.api.reference.menu.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants.0.name');

        $payload['variants'][0]['name'] = 'Group (4-2 servings)';
        $this->postJson(route('admin.api.reference.menu.store'), $payload)
            ->assertUnprocessable();

        $this->assertDatabaseMissing('menu_items', ['name' => 'Invalid serving item']);
    }

    public function test_admin_can_upload_replace_and_remove_menu_images(): void
    {
        Storage::fake('public');
        $item = $this->createMenuItem('Photo dish', true, true);
        $admin = User::query()->create([
            'name' => 'Administrator',
            'email' => 'image-admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $upload = $this->actingAs($admin)
            ->postJson(route('admin.api.reference.menu.image', $item), [
                'image' => UploadedFile::fake()->image('dish.jpg'),
            ])
            ->assertOk();
        $this->assertStringStartsWith('/storage/menu-items/', $upload->json('image_url'));

        $firstPath = $item->fresh()->image_path;
        $this->assertNotEmpty($firstPath);
        Storage::disk('public')->assertExists($firstPath);
        $upload = $this->postJson(route('admin.api.reference.menu.image', $item), [
            'image' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertOk();
        $path = $item->fresh()->image_path;
        $this->assertNotEmpty($path);
        $this->assertNotSame($firstPath, $path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($path);
        $this->get(route('menu'))->assertOk()->assertSee('/storage/'.$path, false);

        $this->deleteJson(route('admin.api.reference.menu.image.delete', $item))
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertNull($item->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_can_upload_high_resolution_menu_images_over_five_megabytes(): void
    {
        Storage::fake('public');
        $item = $this->createMenuItem('High resolution dish', true, true);
        $admin = User::query()->create([
            'name' => 'Administrator',
            'email' => 'high-resolution-image-admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $upload = $this->actingAs($admin)
            ->postJson(route('admin.api.reference.menu.image', $item), [
                'image' => UploadedFile::fake()->image('high-resolution.jpg')->size(6144),
            ])
            ->assertOk();

        $this->assertStringStartsWith('/storage/menu-items/', $upload->json('image_url'));
        $path = $item->fresh()->image_path;
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    private function createMenuItem(string $name, bool $active, bool $available): MenuItem
    {
        $category = MenuCategory::query()->firstOrCreate(
            ['slug' => 'test-category'],
            ['name' => 'Test category', 'is_active' => true],
        );

        return MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => null,
            'price' => 100,
            'is_active' => $active,
            'is_available' => $available,
        ]);
    }
}
