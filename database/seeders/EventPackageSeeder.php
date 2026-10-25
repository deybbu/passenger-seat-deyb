<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EventPackage;
use Illuminate\Database\Seeder;

class EventPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Domestic Flight Platter',
                'min_guests' => 10,
                'max_guests' => 15,
                'fixed_price' => 7999,
                'description' => 'Filipino Comfort Grill: Pork Sisig, Inihaw na Liempo, Chicken BBQ, Fried Lumpiang Shanghai, Ensaladang Mangga, 2 Towers of Iced Tea, and Garlic Rice.',
            ],
            [
                'name' => 'Regional Flight Platter',
                'min_guests' => 16,
                'max_guests' => 20,
                'fixed_price' => 11499,
                'description' => 'Land & Sea Adventure: Everything in the Domestic Flight, plus Crispy Pata, Buttered Shrimp, Grilled Tilapia, and Java Rice.',
            ],
            [
                'name' => 'International Flight Platter',
                'min_guests' => 21,
                'max_guests' => 30,
                'fixed_price' => 17999,
                'description' => 'Premium Feast: Everything in the Regional Flight, plus Garlic Butter Crabs, Grilled Squid, Baked Scallops, Pancit Canton, and upgraded dessert platters.',
            ],
        ];

        foreach ($packages as $package) {
            EventPackage::query()->updateOrCreate(
                ['name' => $package['name']],
                [...$package, 'price_per_guest' => null, 'is_active' => true],
            );
        }
    }
}
