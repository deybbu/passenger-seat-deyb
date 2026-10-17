# Passenger Seat Restaurant — Laravel 12

This repository contains the Passenger Seat restaurant application, built on Laravel 12.
It includes migrations, models, role middleware, routes, controllers, seed data, and Blade views for the core workflows.

See [docs/PROJECT-STRUCTURE.md](docs/PROJECT-STRUCTURE.md) for where the application
code, views, assets, database files, and generated dependencies live.

## Requirements
- PHP 8.2+
- Laravel 12
- PostgreSQL (including Supabase PostgreSQL) or another Laravel-supported relational database
- Node.js/npm for the existing Vite/Tailwind frontend

## Merge into another Laravel app
The following steps describe how to integrate this project's application files into a separate Laravel 12 installation.
1. Copy the `app`, `database`, `resources`, and `routes` folders into that Laravel project, merging files carefully.
2. Configure `.env` database values and run `php artisan key:generate`.
3. Install Laravel's official authentication starter kit (Breeze Blade is a practical option) and ensure the `auth` routes/views exist. This pack deliberately does not invent a custom password-reset/registration system.
4. Check `bootstrap/app.php` if you want to register middleware aliases. The routes below use Laravel's built-in `can` middleware and a `role` alias; register it as described below.
5. Add to `bootstrap/app.php` inside `withMiddleware`:
   ```php
   $middleware->alias([
       'role' => \App\Http\Middleware\EnsureRole::class,
   ]);
   ```
6. Run:
   ```bash
   php artisan migrate --seed
   npm install
   npm run build
   php artisan serve
   ```
7. Create staff users securely through a trusted seeder, Tinker, or an admin-only user-management screen. Do not expose public staff registration.

## Important production notes
- Review table-number policy, payment provider integration, tax/service-charge rules, cancellation/refund rules, privacy notice, retention, backup, logging, and deployment configuration before launch.
- Payment is recorded as a staff-confirmed status only. No payment gateway is included.
- The menu page is public. Internal routes are authenticated and role-protected.
- Inventory quantities are updated by explicit stock logs. Recipe-level automatic ingredient deductions are not included because recipe mappings were not part of the requested schema.
- For production concurrency, use PostgreSQL transactions and row locks (included in checkout stock validation where relevant); test with your actual database and deployment.
- Use HTTPS, secure cookies, rate limiting, least-privilege database credentials, regular dependency updates, and a real monitoring/backup strategy.
