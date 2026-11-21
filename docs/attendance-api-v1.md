# Humexa Attendance API v1

Base URL: https://{tenant}.humexa.test/api/v1

Auth: Bearer token (Laravel Sanctum). Tenant context is resolved from tenant domain. Employee identity is resolved from the authenticated account; client-provided tenant/employee IDs are never used for self-service events.

## Endpoints

| Method | Endpoint | Notes |
|---|---|---|
| GET | /attendance/policy | Company attendance settings exposed to employee app |
| GET | /attendance/status | Current employee status for server-local date |
| GET | /attendance/history?from=&to=&per_page= | Paginated employee history |
| POST | /attendance/check-in | Record check-in (JSON or multipart) |
| POST | /attendance/check-out | Record check-out (JSON or multipart) |

## Event request

- latitude: optional numeric -90..90 (required by policy)
- longitude: optional numeric -180..180 (required by policy)
- accuracy: optional integer meters (required by policy)
- selfie: optional JPEG/PNG up to 5MB (required by policy)
- client_timestamp: optional timestamp; server timestamp is authoritative

## Errors

- 401 unauthenticated
- 403 account/tenant mismatch
- 404 tenant, employee, or policy not found
- 422 policy/validation/state failure

The API contract is an initial draft, not yet production-approved. Timezone, offline queueing/idempotency, anti-spoofing, retention, and device-specific integration need separate decisions and verification.
