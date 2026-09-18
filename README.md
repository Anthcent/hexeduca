# hexeduca

Multi-tenant school management platform. Laravel 12 + Inertia.js + Vue 3, modular (nwidart/laravel-modules), subdomain-based tenancy.

## Stack

- **Backend**: Laravel 12, PHP 8.3, PostgreSQL, Redis, Laravel Reverb (broadcasting), Horizon (queues)
- **Frontend**: Vue 3 + Inertia.js + Tailwind CSS, Vite
- **Modules**: `Users`, `Academic`, `Admin`, `Schedule`, `Grades`, `Files`, `Notifications` (nwidart/laravel-modules, hexagonal-leaning: `Domain` / `Application` / `Infrastructure` per module)
- **Multi-tenancy**: shared-database, single-schema, subdomain-identified (`{tenant}.app.com`) — see `TENANCY.md`

## Local development

```bash
./vendor/bin/sail up -d
npm install && npm run dev   # or npm run build for production assets
```

See `TENANCY.md` for local subdomain/hosts-file setup and `openspec/` for the change history of this project (Spec-Driven Development artifacts).

## Tests

```bash
php artisan test
```
