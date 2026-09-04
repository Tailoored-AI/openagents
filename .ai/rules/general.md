---
paths:
  - '**'
---

# General

## Run everything through Laravel Sail
This project runs on Sail (compose.yaml: laravel.test on PHP 8.5, postgres:18-alpine, mailpit). Use `./vendor/bin/sail artisan|pest|npm|composer ...`, not the host equivalents.

- App is at http://localhost:8000 (APP_PORT maps container :80), Vite at :5173, Mailpit UI at :8025.
- The DB is PostgreSQL, not SQLite. `DB_HOST=pgsql` only resolves inside the container; host-side `php artisan` cannot reach it. Tests use the `testing` database created by Sail's init SQL.
- `node_modules` is bind-mounted and holds Linux binaries installed via `sail npm install`. Running `npm` on the host replaces them with macOS builds and breaks `sail npm run dev` (and vice versa) — always install through Sail.
- `artisan dev` drops its bundled `serve` process when `config('app.sail')` is true, because Sail's supervisor already serves the app. See app/Providers/AppServiceProvider.php.
