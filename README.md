<div align="center">

# Humexa HRMS

**A modular, multi-tenant Human Resources Management System**

Manage people, organization structure, and employee records through a secure, tenant-isolated platform.

</div>

---

## Overview

**Humexa** is a Human Resources Management System (HRMS) built to support organizations through a centralized platform while keeping each company's data isolated.

The application follows a **Modular Monolith** architecture with Laravel on the backend and Vue 3 + Inertia.js on the frontend. It uses a **single-database tenancy model**, with tenant context resolved by domain and tenant-owned records scoped at the application level.

Humexa is under active development. Features are delivered incrementally, and the implementation status described below reflects the current codebase—not a claim that every planned HR workflow is production-ready.

## Key Capabilities

### Platform and tenant management
- Separate platform and tenant authentication flows.
- Tenant registration and domain-based tenant resolution.
- Tenant lifecycle controls, including activation and suspension.
- Tenant-aware access middleware and authorization boundaries.

### Organization management
- Branches.
- Departments.
- Positions.
- Relationships between employees and organizational records.

### Employee management
- Employee profiles and employment information.
- Employment status and reporting relationships.
- Multiple addresses, with a single primary address per employee.
- Multiple emergency contacts with priority ordering.
- National ID and passport records with encrypted values, keyed blind indexes, and masked display values.
- Tenant-managed employee document types.
- Private employee document uploads, previews, and downloads.
- Optional employee profile photos stored privately.

> **Implementation note:** Employee profile and related-record backend capabilities are being built and verified incrementally. Frontend workflows and broader HR processes continue to evolve. Check the current routes, module code, and automated tests before relying on a capability in a production workflow.

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Vue 3, Inertia.js 2 |
| Styling | Tailwind CSS |
| Database | MySQL |
| Cache and queues | Redis or the configured Laravel drivers |
| Multi-tenancy | `stancl/tenancy` 3.10 — single-database mode |
| Authorization | Spatie Laravel Permission with Teams |
| Frontend tooling | Vite |
| Automated testing | Pest |

## Architecture

Humexa uses a **Modular Monolith**: one deployable Laravel application, organized into business-focused modules with clear responsibilities.

```text
app/
├── Modules/
│   ├── Employees/
│   ├── Organization/
│   ├── Tenants/
│   └── Users/
├── Http/
│   └── Middleware/
└── Models/

config/
database/
├── factories/
├── migrations/
└── seeders/

resources/
└── js/

routes/
├── web.php
└── tenant.php

tests/
├── Feature/
└── Unit/
```

The module list reflects the current application structure. Additional modules should be introduced as their business workflows are implemented.

### Multi-tenancy model

Humexa uses single-database tenancy:

- A shared database stores platform and tenant data.
- Tenant-owned records are associated with a tenant.
- Tenant context is initialized from the tenant's domain.
- Tenant queries and business operations must enforce tenant ownership.
- Tenant IDs are resolved server-side; values submitted by the browser are never trusted for tenant authorization.
- Platform access is separated from tenant access through dedicated authentication and middleware.

### Local domain convention

| Application area | Local domain |
|---|---|
| Central platform | `humexa.test` |
| Tenant application | `{tenant-slug}.humexa.test` |

The local domain configuration must match the central domains and tenant-domain settings in the application configuration.

## Getting Started

### Prerequisites

Install the following before setting up the project:

- PHP 8.3 or later, with the extensions required by Laravel.
- Composer.
- Node.js and npm.
- MySQL 8.x.
- Redis, if using Redis for cache, queues, or sessions.
- Git.
- A local web server or a Laravel-compatible development environment.

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

### 3. Configure the environment

Create your local environment file.

**Windows PowerShell:**

```powershell
Copy-Item .env.example .env
```

**macOS / Linux:**

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Update the values in `.env` for your local environment. At minimum, configure:

- `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`
- Redis connection values if Redis-backed services are enabled
- The tenant central-domain configuration required by your local setup

For a local MySQL setup, use `DB_CONNECTION=mysql` and provide the correct database credentials.

### 4. Configure the employee identity hash key

Employee identity numbers are encrypted at rest and use a separate keyed HMAC blind index for duplicate detection.

Generate a dedicated secret of at least 32 characters and set it as:

```dotenv
EMPLOYEE_IDENTITY_HASH_KEY=your-own-random-secret-of-at-least-32-characters
```

Keep this value private, stable, and separate from `APP_KEY`. Do not commit it, share it in issue reports, or reuse the example value above. Losing or changing this key affects identity-number lookup and duplicate detection for existing records; plan key rotation deliberately.

### 5. Configure local domains

Add the central domain and the tenant subdomains you need to your hosts file, pointing them to your local development server.

On Windows, edit the hosts file as Administrator:

```text
C:\Windows\System32\drivers\etc\hosts
```

Example entries:

```text
127.0.0.1 humexa.test
127.0.0.1 acme.humexa.test
```

The Windows hosts file does not support wildcard subdomains. Add an entry for each local tenant domain you use.

If needed, flush the DNS cache:

```powershell
ipconfig /flushdns
```

Make sure your local web server accepts requests for the configured domains and routes them to the Laravel application.

### 6. Run database migrations

Create the configured MySQL database, then run:

```bash
php artisan migrate
```

Review the available seeders before running them. Do not run demo or development seeders against a database containing data you need to preserve.

### 7. Start the application

Start the Laravel application using your local environment. For the standard Laravel development server:

```bash
php artisan serve --host=127.0.0.1
```

In a separate terminal, start Vite:

```bash
npm run dev
```

For a production frontend build:

```bash
npm run build
```

If your local environment uses Laragon, Herd, or another web server, use its normal site-serving workflow instead of starting a second web server with `php artisan serve`.

## Application Areas

| Area | Local URL pattern |
|---|---|
| Central application | `http://humexa.test` |
| Platform sign-in | `http://humexa.test/platform/login` |
| Platform dashboard | `http://humexa.test/platform/dashboard` |
| Tenant sign-in | `http://{tenant-slug}.humexa.test/login` |
| Tenant dashboard | `http://{tenant-slug}.humexa.test/dashboard` |

These URLs assume the local domain configuration described above.

## Configuration Reference

The following environment variables are relevant to employee records and private file storage:

| Variable | Purpose |
|---|---|
| `EMPLOYEE_IDENTITY_HASH_KEY` | Dedicated secret used for identity-number blind indexing |
| `EMPLOYEE_PROFILE_PHOTO_DISK` | Filesystem disk for private employee profile photos |
| `EMPLOYEE_DOCUMENT_DISK` | Filesystem disk for private employee documents |

Profile photos and employee documents must use private storage. Files should be served through authenticated, tenant-authorized application routes—not public storage URLs.

## Testing

Run the complete automated test suite:

```bash
php artisan test
```

Run a focused feature test:

```bash
php artisan test --filter=EmployeeAddressesTest
```

Use the relevant test class name when targeting another feature.

When adding or changing a feature, tests should cover:

- Expected business behavior.
- Validation and invalid input.
- Authentication and authorization.
- Tenant isolation and cross-tenant access attempts.
- Database integrity and transaction behavior.
- Failure scenarios, including file-storage failures where relevant.

A feature should not be considered complete merely because it works in the browser.

## Security and Data Protection

Security and tenant isolation are core architectural requirements.

- Resolve tenant context from the authenticated tenant domain and session.
- Never trust a frontend-supplied `tenant_id`.
- Scope tenant-owned queries and relationships to the active tenant.
- Enforce authorization through policies and protected routes.
- Keep platform and tenant authentication contexts separate.
- Validate input through Laravel Form Requests.
- Use database transactions for operations that modify related records.
- Encrypt sensitive employee identity values at rest.
- Keep identity blind-index keys separate from the Laravel application key.
- Store employee photos and documents on private disks.
- Stream private files only after tenant and employee authorization.
- Do not expose storage paths, encryption values, blind indexes, or confidential employee data in API responses or logs.
- Apply schema changes through reviewed Laravel migrations.

## Development Guidelines

Contributions and new features should follow these conventions:

1. Keep business logic inside the appropriate module.
2. Prefer focused Actions, Services, Policies, Events, and Jobs where they improve separation of concerns.
3. Use Form Requests for request validation.
4. Use database transactions for related writes.
5. Keep tenant scoping and authorization explicit.
6. Preserve existing behavior when modifying a feature.
7. Add Pest coverage for business rules, permissions, tenant boundaries, and edge cases.
8. Avoid unnecessary packages and cross-module dependencies.
9. Keep user-facing interfaces responsive and accessible.
10. Support English and Arabic, including RTL layout requirements, as localization is implemented.

## Roadmap

Humexa is being developed in stages. Planned capabilities include:

- Attendance management, flexible shifts, breaks, corrections, and overtime approval.
- Leave management and approval workflows.
- Payroll and compensation workflows.
- Recruitment and performance management.
- Reporting, notifications, and additional platform administration capabilities.
- A permission-aware, read-only AI assistant for HR data, with any future write-capable AI actions treated as a separate phase.

Roadmap items are plans, not statements of current availability.

## Project Status

**Active development — not yet a production-ready release.**

Before deploying Humexa for real employee data, complete a production-readiness review covering authentication, authorization, tenant isolation, backups, file storage, encryption-key management, monitoring, queue processing, rate limiting, and operational recovery.

## License

No open-source license has been declared for this repository. Unless and until a license is added, all rights are reserved.
