# Employees Module

## Scope
The first Employees release provides tenant-scoped employee profile management:
- Employee directory with search, status filter, pagination, and summary counts.
- Create, view, update, and archive employee profiles.
- Optional link to an existing tenant user account.
- Automatic or manually supplied employee numbers.
- Branch, department, position, and direct-manager assignment.
- Employment status history.

## Employment statuses
- draft: profile can be created before a hire date is known.
- probation
- active
- on_leave
- suspended
- resigned
- terminated
A hire date is required for every status except draft. A termination date is required for resigned and terminated.

## Employee numbers
If employee_number is omitted during creation, the application generates a tenant-local number using the EMP-0001 format. The tenant row is locked inside the creation transaction to serialize automatic number generation. Manually supplied numbers are accepted, and the database enforces uniqueness per tenant. Archived employee numbers are not reused.

## Database
### employees
Stores employee identity, contact details, employment dates/status, optional user account, organization assignment, and manager relationship.

### employee_employment_histories
Stores the initial employment status and each subsequent status change, including the previous/new status, effective date, optional reason, and actor.

Composite foreign keys enforce tenant ownership for user, branch, department, position, manager, and history relationships. tenant_id is always assigned by backend tenant context, never accepted from the request.

## Security
- Tenant routes require auth:tenant and tenant.user middleware.
- Employee Policies verify that the authenticated tenant user and employee belong to the initialized tenant.
- Queries are explicitly scoped to the current tenant.
- Form Requests validate organization and user ownership.
- Reporting relationships are checked to prevent self-management and circular manager chains.
- Archiving a manager with direct reports is blocked with HTTP 409.
- Archiving a branch, department, or position assigned to employees is blocked with HTTP 409.
- The initial release grants employee management to authenticated tenant users. Fine-grained Spatie permissions are a separate authorization phase.

## Routes
- GET /employees — directory
- GET /employees/create — create form
- POST /employees — create
- GET /employees/{employee} — profile
- GET /employees/{employee}/edit — edit form
- PUT /employees/{employee} — update
- DELETE /employees/{employee} — archive

## Deferred
Employee documents, national ID, addresses, emergency contacts, contracts, compensation, and detailed employment/organization assignment history are intentionally outside this first release.