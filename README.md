# PitchFlow

PitchFlow is a bilingual, multi-tenant Laravel SaaS application for football field reservation management. It provides organization approval, field and employee access controls, a transaction-safe reservation calendar, private customer histories, reliability scoring, reports, and anonymous availability lookup.

## Stack

- PHP 8.4 and Laravel 12
- React 19, Inertia.js, TypeScript, Tailwind CSS 4
- MySQL for local and production use; PostgreSQL-compatible schema
- Database-backed queues, cache, and sessions

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the MySQL database configured in `.env`, then run:

```bash
php artisan migrate --seed
npm run build
composer run dev
```

The development command starts Laravel, the queue worker, application logs, and Vite.

## Initial Administrator

Set `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, and `SUPER_ADMIN_PASSWORD` in the local environment before seeding. These values are never committed.

```bash
php artisan db:seed
```

After login, the administrator can approve organizations and manage supported cities.

## Quality Checks

```bash
composer quality
npm run typecheck
npm run lint
npm run build
```

Critical tests cover organization isolation, employee field permissions, approval access, reservation conflicts, operating hours, cancellation behavior, public privacy, and customer reliability.

## Production

Configure HTTPS, a persistent queue worker, and the Laravel scheduler. Deploy with:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

Run `php artisan queue:work --tries=3` under a process supervisor and execute `php artisan schedule:run` every minute. Set `APP_ENV=production`, `APP_DEBUG=false`, secure session cookies, trusted mail credentials, and production cache/session/queue stores. Back up the database and application encryption key separately.

The product stores reservation timestamps in UTC. Organizations retain the approved `Europe/Pristina` product timezone identifier, mapped internally to the equivalent IANA `Europe/Belgrade` rules because PHP does not currently expose a `Europe/Pristina` timezone.
