<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $category = MenuCategory::query()->create([
            'name' => 'Test favorites',
            'slug' => 'test-favorites',
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Test featured dish',
            'slug' => 'test-featured-dish',
            'price' => 100,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    public function test_homepage_uses_reference_layout_and_static_sections(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Passenger Seat')
            ->assertSee('Your table is boarding')
            ->assertSee('Fly through our menu')
            ->assertSee('data-menu-card', false)
            ->assertSee('Fresh from the kitchen')
            ->assertSee('A journey, one shared plate at a time')
            ->assertSee('Cabin-style seating')
            ->assertSee('Take your seat')
            ->assertSee('Indulge in an unforgettable dining journey')
            ->assertSee('Open in Google Maps')
            ->assertSee('href="'.route('menu').'"', false)
            ->assertSee('href="'.route('events').'"', false)
            ->assertDontSee('Sample boodle fight types')
            ->assertDontSee('Admin / Sign out');
    }
}
