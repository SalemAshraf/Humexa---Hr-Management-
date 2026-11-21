# Company roles and permissions

Each company gets four roles (Owner, HR Admin, Manager, Employee) and ten permissions
(see `App\Modules\Users\Support\TenantRoleCatalog`). The Employee role has no
company-wide permission.

- New companies: the registrant receives Owner inside the registration transaction.
- Existing (legacy) companies: the migration creates roles and permissions only. No
  account is promoted automatically, because nothing records who created a legacy
  company. Until mapped, those accounts have no company-wide access (fail closed).
- Mapping an owner is an operator action, run only after verifying the person:
  `php artisan app:assign-tenant-owner {tenant-id} {email} --confirm`
  The account must already belong to that company.
- Routes use the `tenant.can:{read},{write}` middleware (GET needs read, other methods need write).
