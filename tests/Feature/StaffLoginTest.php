<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    public function test_staff_login_is_available_at_its_separate_route(): void
    {
        $this->get('/staff/login')->assertOk();
    }

    public function test_kitchen_and_inventory_staff_are_sent_to_their_own_home_pages(): void
    {
        foreach ([
            ['kitchen', route('staff.kitchen.index')],
            ['inventory', route('inventory.index')],
        ] as [$role, $destination]) {
            $user = User::query()->create([
                'name' => ucfirst($role).' Staff',
                'email' => $role.'-login@example.test',
                'password' => Hash::make('password'),
                'role' => $role,
            ]);

            $this->withSession(['url.intended' => route('staff.pos.index')])
                ->post(route('staff.login.attempt'), [
                    'email' => $user->email,
                    'password' => 'password',
                ])
                ->assertRedirect($destination);

            $this->get($destination)->assertOk();
            $this->post(route('staff.logout'));
        }
    }

    public function test_customers_cannot_access_staff_or_admin_pages(): void
    {
        $customer = new User(['name' => 'Customer', 'email' => 'customer@example.com', 'role' => 'customer']);

        $this->actingAs($customer)
            ->get('/staff/orders')
            ->assertForbidden();

        $this->get('/admin/dashboard')->assertForbidden();
    }

    public function test_staff_login_attempts_are_rate_limited(): void
    {
        Auth::shouldReceive('userResolver')->andReturn(fn () => null);
        Auth::shouldReceive('attempt')->times(5)->andReturn(false);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/staff/login')
                ->post('/staff/login', ['email' => 'staff@example.com', 'password' => 'wrong'])
                ->assertRedirect('/staff/login');
        }

        $this->post('/staff/login', ['email' => 'staff@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}