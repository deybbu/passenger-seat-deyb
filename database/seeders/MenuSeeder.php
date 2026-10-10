<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menu = [
            'Flight Boodle Board (Communal Feasts)' => [
                ['Manila Flight', 840, 'A curated regional bundle featuring grilled pork liempo, authentic chicken inasal, classic pancit bihon, steamed rice, and local dynamic sides. Highly recommended for a casual pair. Good for 2–3 pax.'],
                ['Palawan Flight', 840, 'Seafood-forward spread containing crispy daing na bangus (milkfish), buttered garlic shrimp, local salted egg ensalada, and unlimited-style garlic rice. Good for 2–3 pax.'],
                ['Caticlan Flight', 1230, 'Medium-tier premium feast stacked with pork barbecue skewers, lechon kawali, buttered shrimp, grilled stuffed squid, eggplant sides, and fresh seasonal fruits. Good for 3–5 pax.'],
                ['LuzViMinda Premium Flight', 3480, 'The master ultimate flight grouping. Features massive portions of crispy lechon kawali, pork barbecue, chicken inasal, sweet-style chili shrimp, grilled tilapia, lumpia wrapper rolls, ensalada, deep-fried eggplant, rice, local desserts, and fresh fruits. Good for 8–10 pax.'],
            ],
            'Single Passenger Flights (A La Carte)' => [
                ['Tagaytay Bulalo', 350, 'Slow-simmered premium beef shank and marrow soup with local greens and corn cob discs. Good for sharing.'],
                ['Vigan Sinigang na Lechon Kawali', 199, 'Crispy pork belly blocks submerged in a traditional, sour, tamarind-base native broth.'],
                ['Sampaloc Hipon Sinigang', 189, 'Sourced fresh prawns cooked beautifully inside a sharp sour tamarind broth alongside string beans and radish.'],
                ['Grilled Stuffed Squid', 179, 'Whole ocean squid plumped with diced tomatoes, onions, and local spices, glazed with a dark sweet barbecue brush.'],
                ['Dina Dakan Pork', 169, 'An authentic Ilocano delicacy featuring chopped grilled pork parts tossed gently with red onions, chili flakes, and a smooth local dressing.'],
                ['Kyoto Sweet Glazed Wings', 159, 'Six structural pieces of crispy fried chicken wings tossed inside an authentic sweet soy teriyaki glaze.'],
            ],
            'Airport Lounge Starters & Sides' => [
                ['Brussels Special Cheesy Beef Fries', 99, 'Thick cut potatoes smothered in a rich warm cheese sauce and capped with seasoned ground beef bits.'],
                ['Paris Classic Salted Fries', 65, 'Golden shoestring cut potatoes lightly tossed in fine sea salt crystals.'],
            ],
            'Cabin Crew Sweets & Beverages' => [
                ['Sizzling Table Dessert Brownies', 125, 'Fudge brownies served on a screaming-hot iron skillet plate, drizzled heavily with warm chocolate sauce and cold vanilla ice cream.'],
                ['Premium Fruit Cabin Shakes', 125, 'Blended fresh mango or avocado milk mixtures capped with sweet whipped cream.'],
            ],
        ];

        $categoryOrder = 1;

        foreach ($menu as $categoryName => $items) {
            $category = Category::updateOrCreate(
                ['slug' => str($categoryName)->slug()->toString()],
                ['name' => $categoryName, 'sort_order' => $categoryOrder++]
            );

            foreach ($items as $position => [$name, $price, $description]) {
                $category->items()->updateOrCreate(
                    ['name' => $name],
                    ['description' => $description, 'price' => $price, 'is_available' => true, 'sort_order' => $position + 1]
                );
            }
        }
    }
}
