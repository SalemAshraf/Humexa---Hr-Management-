# Employees Module

## Scope
The Employees module provides tenant-scoped employee profile management:
- Employee directory with search, status filter, pagination, and summary counts.
- Create, view, update, and archive employee profiles.
- Optional link to an existing tenant user account.
- Automatic or manually supplied employee numbers.
- Branch, department, position, and direct-manager assignment.
- Employment status history.
- Multiple addresses and emergency contacts per employee.
- National ID and passport records with one current record per type and retained history.
- Private employee profile photos and private employee document metadata/storage.

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

## Phase 2 database
- employees gains personal_email, alternate_phone, nationality, marital_status, and profile_photo_path.
- employee_addresses supports multiple addresses and enforces one primary address per employee with a generated column and unique index.
- employee_emergency_contacts supports multiple contacts and unique per-employee priorities.
- employee_identity_records stores encrypted identity numbers, HMAC blind indexes, one current record per identity type, and retained historical records.
- employee_document_types stores tenant-configurable document categories.
- employee_documents stores private file metadata and expiry information.

All employee-owned tables include tenant_id and composite foreign keys to the employee. Tenant-owned relationships are protected with composite foreign keys. Tenant IDs are assigned by backend tenant context and never accepted from client input.

## Identity security configuration
Set EMPLOYEE_IDENTITY_HASH_KEY to a dedicated random secret of at least 32 characters in every application environment before creating identity records. Do not reuse APP_KEY. The key must remain stable across deployments. Identity values are encrypted using Laravel's encrypted cast (APP_KEY); the HMAC key is used only for deterministic duplicate detection. Never log identity numbers or expose the ciphertext/hash to clients.

Changing either encryption or HMAC keys requires a planned rotation and data re-encryption/re-indexing procedure. Do not replace keys directly in production.

## File storage
Profile photos and employee documents use private disks configured through EMPLOYEE_PROFILE_PHOTO_DISK and EMPLOYEE_DOCUMENT_DISK. Files must be served through authenticated, tenant-authorized controller actions; do not expose private storage paths or generate public URLs. Validate actual MIME type and size on upload. Database rollback does not delete stored files.

Recommended initial limits: profile photo 5 MB; employee documents 10 MB; PDF/JPG/JPEG/PNG documents.

## Security
- Tenant routes require auth:tenant and tenant.user middleware.
- Employee Policies verify that the authenticated tenant user and employee belong to the initialized tenant.
- Queries are explicitly scoped to the current tenant.
- Form Requests validate organization and user ownership.
- Reporting relationships are checked to prevent self-management and circular manager chains.
- Archiving a manager with direct reports is blocked with HTTP 409.
- Sensitive identity values must be masked in UI responses and excluded from logs.
- Document and photo downloads require tenant ownership and authorization checks.
- Fine-grained Spatie permissions are a separate authorization phase.

## Routes
The employee directory/profile routes and nested address endpoints are registered in routes/tenant.php under the authenticated tenant route group. Address endpoints are `GET/POST /employees/{employee}/addresses` and `PUT/DELETE /employees/{employee}/addresses/{address}`. Emergency-contact endpoints are `GET/POST /employees/{employee}/emergency-contacts` and `PUT/DELETE /employees/{employee}/emergency-contacts/{contact}`. Employee-owned records are resolved through the current tenant and parent employee relationship; list endpoints return a JSON `data` collection while mutations redirect back with a flash message. Emergency-contact priority is assigned sequentially by the backend on creation; clients cannot submit tenant IDs, employee IDs, or priority values.

## Testing
Pest feature tests must cover tenant isolation, composite constraints, current identity uniqueness, primary address uniqueness, emergency-contact ordering, upload validation, private file access, and rollback/failure cleanup.
