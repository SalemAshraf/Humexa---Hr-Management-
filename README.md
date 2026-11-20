import pypandoc, os
readme = r'''# Humexa HRMS

**Humexa** is a multi-tenant Human Resources Management System (HRMS) built with Laravel, Vue 3, Inertia.js, and Tailwind CSS.

The project follows a **Modular Monolith** architecture and uses **single-database tenancy**. It is being developed incrementally, with tenant isolation, authorization, and maintainability treated as core requirements.

> **Project status:** Active development. The current codebase includes the application foundation, tenant and platform authentication flows, and initial dashboards. Not all HR modules or dashboard actions are implemented yet.

## Technology Stack

| Area | Technology |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Vue 3, Inertia.js 2 |
| Styling | Tailwind CSS |
| Database | MySQL |
| Cache, sessions and queues | Redis |
| Multi-tenancy | `stancl/tenancy` (single-database mode) |
| Roles and permissions | Spatie Laravel Permission (Teams enabled) |
| Testing | Pest |
| Build tooling | Vite |

## Architecture

Humexa is organized as a modular monolith. Business capabilities are separated into modules while remaining part of one Laravel application.

The intended high-level structure is:

```text
app/
├── Modules/
│   ├── Users/
│   ├── Tenants/
│   └── ...
└── Shared/
```

Modules are introduced as the corresponding business capabilities are developed; this structure does not imply that every planned module is already implemented.

### Multi-tenancy model

- Single MySQL database shared by tenants.
- Tenant-owned records are scoped using `tenant_id`.
- Tenant context is resolved from the tenant's domain.
- Tenant-owned data must not be accessed across tenant boundaries.
- Platform administration is separated from tenant administration.

### Local domains

- Central platform: `humexa.test`
- Tenant domains: `{tenant-slug}.humexa.test`

For local Windows development, add the central domain and each tenant subdomain you want to use to the Windows hosts file (`C:\Windows\System32\drivers\etc\hosts`), pointing them to `127.0.0.1`. The Windows hosts file does not provide wildcard subdomain resolution.

## Current Foundation

Implemented and verified in the current development environment:

- Tenant registration flow that creates a tenant, its domain, and the initial tenant administrator within a database transaction.
- Separate tenant and platform authentication guards and login flows.
- Tenant and platform access middleware.
- Tenant-aware user providers.
- Team-scoped roles and permissions configuration.
- Initial tenant dashboard and platform dashboard.
- Redis configured for cache, queue, and session storage.
- Pest test suite covering the current application foundation and permission-team isolation.

The following are planned or under development and should **not** be considered complete:

- Full employee management and organization structure.
- Attendance, shifts, breaks, overtime approval, and attendance corrections.
- Leave management.
- Payroll.
- Recruitment and performance management.
- Reports, notifications, subscriptions, billing, and platform administration screens.
- AI read-only HR assistant. This is a planned capability, not an implemented feature.

## Local Development Setup

### Requirements

Install and configure:

- PHP 8.3 or later, with the extensions required by Laravel.
- Composer.
- Node.js and npm.
- MySQL.
- Redis.
- Git.

### 1. Clone the repository

```bash
git clone https://github.com/SalemAshraf/Humexa---Hr-Management-.git
cd Humexa---Hr-Management-
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Configure environment

Create your local environment file:

```bash
cp .env.example .env
```

On Windows PowerShell, use:

```powershell
Copy-Item .env.example .env
```

Configure the database and Redis connection values in `.env`. Set the local application URL to the address used by your development environment, for example:

```dotenv
APP_URL=http://humexa.test
```

Generate the application key:

```bash
php artisan key:generate
```

Do not commit `.env` or place real credentials, API keys, or production secrets in `.env.example`.

### 4. Create the database

Create a MySQL database for the application (the current local development database is named `humexa`) and update the `DB_*` values in `.env`.

Run migrations:

```bash
php artisan migrate
```

Do not run seeders blindly against a database containing data. Review `DatabaseSeeder` and the target environment first.

### 5. Configure local domains

Add entries similar to these to the Windows hosts file, adjusting the tenant subdomain to the tenant you registered:

```text
127.0.0.1 humexa.test
127.0.0.1 your-tenant.humexa.test
```

If you change the hosts file, flush the DNS cache:

```powershell
ipconfig /flushdns
```

Configure your local web server or Laravel development server so the domains resolve to the application. If using Laravel's development server, make sure it is listening on an address reachable through the configured local domains.

### 6. Build frontend assets

For development:

```bash
npm run dev
```

For a production build:

```bash
npm run build
```

### 7. Run the application

Start the Laravel development server if your local environment does not already serve the application:

```bash
php artisan serve
```

Keep the Vite development process running in a separate terminal when using `npm run dev`.

## Application Portals

| Portal | Local URL |
|---|---|
| Public landing page | `http://humexa.test` |
| Tenant login | `http://{tenant-slug}.humexa.test/login` |
| Tenant dashboard | `http://{tenant-slug}.humexa.test/dashboard` |
| Platform admin login | `http://humexa.test/platform/login` |
| Platform dashboard | `http://humexa.test/platform/dashboard` |

A platform administrator is provisioned through the application's administrative Artisan command:

```bash
php artisan app:create-platform-admin
```

Follow the interactive prompts. Do not create platform administrators through public tenant registration.

## Testing

Run the automated test suite with:

```bash
php artisan test
```

Or run Pest directly:

```bash
./vendor/bin/pest
```

Tests should cover normal behavior, validation, authorization, tenant isolation, and failure scenarios. Always run relevant tests after changing application behavior.

## Security Notes

- Never commit `.env`, credentials, private keys, database dumps, or production data.
- Never trust a `tenant_id` submitted by the frontend. Resolve tenant ownership from the authenticated tenant context.
- Enforce tenant scope in queries, services, policies, and queued jobs.
- Protect platform routes separately from tenant routes.
- Apply authorization to every protected operation.
- Use migrations for schema changes; do not make manual production schema changes.
- Review temporary preview routes and development-only configuration before any production deployment.
- This repository is under active development and is not a production deployment guide.

## Development Principles

- Modular Monolith with clear module responsibilities.
- Laravel conventions and SOLID principles.
- Form Requests for input validation.
- Database transactions for related multi-record operations.
- Explicit authorization and tenant isolation.
- Pest tests for business rules and security boundaries.
- English and Arabic localization, including RTL support, as the frontend is developed.

## Contributing

The project is currently under active development. Before submitting changes:

1. Keep changes focused and consistent with the existing module boundaries.
2. Do not introduce new dependencies without a clear need.
3. Add or update tests for behavior changes.
4. Run the relevant test suite.
5. Never include local environment files, secrets, or generated runtime data in commits.

## License

No open-source license has been specified yet. All rights are reserved unless a license is added to this repository.
'''
out = "/mnt/data/README.md"
pypandoc.convert_text(readme, "md", format="md", outputfile=out, extra_args=["--standalone"])
print(f"Created: {out}")
print(f"Size: {os.path.getsize(out)} bytes")
