<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_packages', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_guests')->nullable()->after('description');
            $table->unsignedSmallInteger('max_guests')->nullable()->after('min_guests');
            $table->decimal('fixed_price', 10, 2)->nullable()->after('price_per_guest');
        });

        foreach ([
            ['Domestic Flight Platter', 'Filipino Comfort Grill: Pork Sisig, Inihaw na Liempo, Chicken BBQ, Fried Lumpiang Shanghai, Ensaladang Mangga, 2 Towers of Iced Tea, and Garlic Rice.', 10, 15, 7999],
            ['Regional Flight Platter', 'Land & Sea Adventure: Everything in the Domestic Flight, plus Crispy Pata, Buttered Shrimp, Grilled Tilapia, and Java Rice.', 16, 20, 11499],
            ['International Flight Platter', 'Premium Feast: Everything in the Regional Flight, plus Garlic Butter Crabs, Grilled Squid, Baked Scallops, Pancit Canton, and upgraded dessert platters.', 21, 30, 17999],
        ] as [$name, $description, $minGuests, $maxGuests, $price]) {
            DB::table('event_packages')->updateOrInsert(
                ['name' => $name],
                [
                    'description' => $description,
                    'min_guests' => $minGuests,
                    'max_guests' => $maxGuests,
                    'fixed_price' => $price,
                    'price_per_guest' => null,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::table('event_packages', function (Blueprint $table): void {
            $table->dropColumn(['min_guests', 'max_guests', 'fixed_price']);
        });
    }
};
