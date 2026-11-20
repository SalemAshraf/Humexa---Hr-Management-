# Organization Module

The Organization module manages tenant-owned company structure before employee records are introduced.

## Scope

- Branches: company locations with name, optional code, city and address.
- Departments: company-wide or branch-linked departments.
- Positions: job titles belonging to a department.
- Active/inactive status and soft archiving.

## Relationships

- Tenant has many branches, departments and positions.
- Branch has many departments.
- Department optionally belongs to a branch and has many positions.
- Position belongs to one department.

A department without a branch is considered company-wide.

## Tenant isolation and integrity

- The tenant ID is always resolved from the initialized Stancl tenancy context.
- The frontend cannot set or override tenant_id.
- Controllers scope reads, updates and archives by the current tenant.
- Form Requests scope foreign-key validation to the current tenant.
- Composite foreign keys prevent a department or position from referencing a parent in another tenant.
- Parent records cannot be archived while child records still reference them.
- Tenant deletion is restricted while organization data exists.

## Uniqueness

Branch names and codes are unique per tenant. Department names and codes are unique per tenant. Position names are unique within a department, and position codes are unique per tenant. Archived identifiers remain reserved to avoid ambiguous historical records.

## Routes

All routes are tenant-domain routes protected by auth:tenant and tenant.user.

- GET /organization
- POST /organization/branches
- PUT /organization/branches/{branch}
- DELETE /organization/branches/{branch}
- POST /organization/departments
- PUT /organization/departments/{department}
- DELETE /organization/departments/{department}
- POST /organization/positions
- PUT /organization/positions/{position}
- DELETE /organization/positions/{position}

## Verification

Focused feature tests:
php artisan test tests/Feature/OrganizationManagementTest.php

Complete suite:
php artisan test

The module currently grants access to authenticated users of the active tenant. Fine-grained Organization permissions should be applied when tenant role assignment and user administration are introduced; they must not be inferred from frontend visibility.
