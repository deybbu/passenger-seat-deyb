<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Starter staff accounts. Set SEED_STAFF_PASSWORD in .env before seeding,
 * and change these passwords before the system is used for real.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_STAFF_PASSWORD', 'ChangeMe!2026');

        foreach ([
            ['Admin', 'admin@passengerseat.test', 'admin'],
            ['Cashier', 'cashier@passengerseat.test', 'cashier'],
            ['Kitchen', 'kitchen@passengerseat.test', 'kitchen'],
            ['Inventory', 'inventory@passengerseat.test', 'inventory'],
        ] as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'role' => $role, 'password' => $password]
            );
        }
    }
}
