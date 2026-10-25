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
            ->assertSee('id="theme"', false)
            ->assertSee('role="switch"', false)
            ->assertSee('class="theme-icon-sun"', false)
            ->assertDontSee('Dark mode')
            ->assertDontSee('Light mode')
            ->assertSee('Your Table Is Boarding')
            ->assertSee('Fly Through Our Menu')
            ->assertSee('data-menu-card', false)
            ->assertSee('class="landing-story-owner"', false)
            ->assertSee('alt="Mariae Habijan-Asuncion, owner of Passenger Seat"', false)
            ->assertDontSee('Fresh from the kitchen')
            ->assertDontSee('href="#whatsnew"', false)
            ->assertSee('Mariae Habijan-Asuncion')
            ->assertSee('The Meaning &amp; Backstory of Passenger Seat', false)
            ->assertSee('<details class="landing-story-feature">', false)
            ->assertSee('<details class="landing-story-chapter">', false)
            ->assertSee('Read more')
            ->assertSee('The Flight Attendant Dream &amp; Inspiration', false)
            ->assertSee('The definitive turning point occurred in 2017')
            ->assertSee('Boodle Flights')
            ->assertDontSee('Cabin-style seating')
            ->assertSee('Take Your Seat')
            ->assertSee('Indulge in an Unforgettable Dining Journey')
            ->assertSee('Open in Google Maps')
            ->assertSee('href="'.route('menu').'"', false)
            ->assertSee('href="'.route('events').'"', false)
            ->assertDontSee('Sample boodle fight types')
            ->assertDontSee('Admin / Sign out');
    }

    public function test_story_page_shows_the_owners_backstory_instead_of_placeholders(): void
    {
        $response = $this->get(route('story'));

        $response
            ->assertOk()
            ->assertSee('Mariae Habijan-Asuncion')
            ->assertSee('class="landing-story-owner-photo"', false)
            ->assertSee('The Meaning &amp; Backstory of Passenger Seat', false)
            ->assertSee('<details class="landing-story-feature">', false)
            ->assertSee('<details class="landing-story-chapter">', false)
            ->assertSee('Early Roots')
            ->assertSee('The Flight Attendant Dream &amp; Inspiration', false)
            ->assertSee('The Turning Point')
            ->assertSee('The Vision')
            ->assertSee('aria-label="Passenger Seat story board"', false)
            ->assertSee('01 / ORIGINS')
            ->assertSee('02 / INSPIRATION')
            ->assertSee('03 / TAKEOFF')
            ->assertSee('04 / DESTINATION')
            ->assertDontSee('Placeholder text')
            ->assertDontSee('Owner photo placeholder')
            ->assertDontSee('Global Maritime Background');

        $this->assertSame(4, substr_count($response->getContent(), 'class="landing-story-chapter"'));
    }

    public function test_landing_menu_keeps_side_scroll_and_links_to_our_story_without_a_card_grid(): void
    {
        $boodleCategory = MenuCategory::query()->create([
            'name' => 'Solo Boodle Flights',
            'slug' => 'solo-boodle-flights',
            'is_active' => true,
        ]);

        foreach (['Boodle for Two', 'Family Boodle'] as $index => $name) {
            MenuItem::query()->create([
                'menu_category_id' => $boodleCategory->id,
                'name' => $name,
                'slug' => 'boodle-'.($index + 1),
                'price' => 500,
                'is_active' => true,
                'is_available' => true,
            ]);
        }

        $response = $this->get(route('home'))->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('id="vScroll"', $html);
        $this->assertStringContainsString('href="#story">Our Story</a>', $html);
        $this->assertStringContainsString('id="story"', $html);
        $this->assertStringNotContainsString('Card grid', $html);
        $this->assertStringNotContainsString('id="vCards"', $html);
        $this->assertStringNotContainsString('id="vShow"', $html);
        $this->assertStringNotContainsString('data-showcase-slide', $html);
        $this->assertSame(3, substr_count($html, 'data-menu-card'));
    }

    public function test_whats_new_shows_every_new_collection_item_and_hides_other_collections(): void
    {
        MenuItem::query()->where('slug', 'test-featured-dish')->update(['is_featured' => true]);
        MenuItem::query()->create([
            'menu_category_id' => MenuCategory::query()->firstOrFail()->id,
            'name' => 'New seasonal dish',
            'slug' => 'new-seasonal-dish',
            'price' => 125,
            'is_active' => true,
            'is_available' => false,
            'collection_type' => 'New',
        ]);
        MenuItem::query()->create([
            'menu_category_id' => MenuCategory::query()->firstOrFail()->id,
            'name' => 'Another new dish',
            'slug' => 'another-new-dish',
            'price' => 150,
            'is_active' => true,
            'is_available' => true,
            'collection_type' => 'New',
        ]);

        $response = $this->get(route('home'))->assertOk();
        $html = $response->getContent();
        $whatsNewStart = strpos($html, 'id="whatsnew"');
        $storyStart = strpos($html, 'id="story"', $whatsNewStart);
        $this->assertNotFalse($whatsNewStart);
        $this->assertNotFalse($storyStart);
        $whatsNewHtml = substr($html, $whatsNewStart, $storyStart - $whatsNewStart);
        $this->assertStringContainsString('Fresh From the Kitchen', $whatsNewHtml);
        $this->assertStringContainsString('New seasonal dish', $whatsNewHtml);
        $this->assertStringContainsString('Another new dish', $whatsNewHtml);
        $this->assertStringNotContainsString('Test featured dish', $whatsNewHtml);
    }
}
