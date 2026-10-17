# Project structure

This repository is a Laravel application. Its source files are kept in Laravel's
expected locations so Composer, Artisan, and Vite can find them without changing
application code or configuration.

## Application source

| Location | Contents |
| --- | --- |
| `app/Http/Controllers/` | Web request controllers, grouped by area such as `Admin/`, `Auth/`, and `Staff/` |
| `app/Http/Middleware/` | HTTP middleware |
| `app/Models/` | Eloquent models |
| `app/Providers/` | Laravel service providers |
| `app/Services/` | Reusable application and business logic |
| `app/Support/` | Shared application support classes |
| `routes/web.php` | Web routes |
| `routes/console.php` | Console command routes |
| `resources/views/` | Blade templates, grouped into areas such as `admin/`, `customer/`, `landing/`, and `layouts/` |
| `resources/css/` | CSS source files processed by Vite |
| `resources/js/` | JavaScript source files processed by Vite |
| `public/` | Public entry point and directly served assets, including images, CSS, and JavaScript |

## Data, configuration, and tests

| Location | Contents |
| --- | --- |
| `database/migrations/` | Database schema changes, in timestamp order |
| `database/seeders/` | Seed data |
| `database/factories/` | Model factories |
| `config/` | Laravel configuration |
| `bootstrap/` | Framework bootstrap and provider registration |
| `tests/Feature/` | Feature tests |
| `tests/Unit/` | Unit tests |

## Root-level files and generated directories

- `artisan` is the Laravel command-line entry point.
- `composer.json` and `composer.lock` define PHP dependencies; `package.json` and
  `package-lock.json` define frontend dependencies.
- `vite.config.js` defines the frontend asset build.
- `README.md` is the project overview; `PHASE1-README.md` describes the earlier
  Phase 1 setup.
- `vendor/` and `node_modules/` contain installed dependencies. They are
  generated locally and are not application source.
- `public/build/` contains built frontend assets. `storage/` contains runtime
  files and logs.

## Keep the project working

Keep new files in the matching Laravel directory and follow the naming patterns
already used there. Do not move or rename source files, migrations, or assets
without also updating every route, view reference, Composer namespace mapping,
and Vite input that depends on the current path. Keep local `.env` credentials
private; share `.env.example` for setup instead.
