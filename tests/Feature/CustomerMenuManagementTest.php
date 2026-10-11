<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMenuManagementTest extends TestCase
{
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

        foreach ([
            '2026_10_10_000001_create_menu_categories_table.php',
            '2026_10_10_000002_create_menu_items_table.php',
        ] as $migration) {
            Artisan::call('migrate', [
                '--database' => 'customer_menu_test',
                '--path' => database_path('migrations/'.$migration),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    public function test_public_menu_only_shows_active_available_items(): void
    {
        $this->createMenuItem('Visible dish', true, true);
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
