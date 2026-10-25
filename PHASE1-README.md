# Passenger Seat Restaurant — Phase 1

This is a set of files to copy into a fresh Laravel 12 project. It is not a complete Laravel framework installation.

## 1. Create the project

```powershell
cd C:\Users\Mika
composer create-project laravel/laravel passenger-seat "^12.0"
cd passenger-seat
npm install
```

## 2. Copy these files into the project

Extract this ZIP into `C:\Users\Mika\passenger-seat` and allow the listed files to merge with the Laravel folders. Review any overwrite prompts; these files intentionally replace `routes/web.php`, `resources/js/app.js`, and `database/seeders/DatabaseSeeder.php`.

## 3. Environment and Supabase

Copy `.env.example` values into your local `.env` as appropriate. Keep your actual `.env` private and do not commit credentials. In Supabase, open Project Settings → Database and obtain the PostgreSQL connection details. Use the correct host, port, database, username, and password for the chosen direct or pooler connection. `DB_SSLMODE=require` is included as an environment value; Laravel's standard pgsql configuration may not consume this variable automatically. If SSL settings need to be explicit, configure the `pgsql` connection's `sslmode` in `config/database.php` using `env('DB_SSLMODE', 'prefer')`.

Then run:

```powershell
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Visit `http://127.0.0.1:8000`.

## 4. Phase 1 scope

- Shared responsive Blade layout and red/white aviation-inspired styling
- Landing page with database-driven featured menu items and a clear empty state
- Our Story and Contact placeholder pages
- Initial menu category and menu item models/migrations
- Safe starter category seeder; no fabricated sales or menu items

## Notes

- Food and restaurant imagery currently uses replaceable remote Unsplash images. Replace them with approved restaurant assets when available.
- No ordering, authentication, admin dashboard, POS, inventory, or barcode receiving is implemented in Phase 1; those belong to later phases.
- Before running migrations against a populated database, inspect the migration plan and back up important data.
