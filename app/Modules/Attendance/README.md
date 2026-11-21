# Humexa Attendance Module

API-first attendance foundation for tenant-scoped attendance with multiple work sessions per employee per day.

## Daily attendance data model

- `attendance_records` is the daily summary: one row per tenant, employee, and attendance date.
- `attendance_sessions` stores each actual work period. A normal workday may contain one session; split shifts may contain multiple sessions.
- Each session owns its check-in/check-out timestamps and sources, breaks, events, correction requests, and overtime requests.
- Daily totals are aggregated from the sessions and are returned with the session collection.

## Current API v1 (tenant domain)

- GET /api/v1/attendance/policy
- GET /api/v1/attendance/status
- GET /api/v1/attendance/history
- POST /api/v1/attendance/check-in
- POST /api/v1/attendance/check-out
- POST /api/v1/attendance/breaks/start
- POST /api/v1/attendance/breaks/end
- POST /api/v1/attendance/corrections
- GET /api/v1/attendance/corrections
- POST /api/v1/attendance/overtime-requests
- GET /api/v1/attendance/overtime-requests

All endpoints use Sanctum authentication and domain-based tenancy. Employee self-service endpoints resolve the employee from the authenticated tenant user; they do not accept employee or tenant IDs from the client.

## Session workflow

- Check-in creates the next session under the employee's daily summary.
- A second check-in is rejected while a session remains open.
- Check-out closes the active session.
- Break and correction/overtime endpoints now accept `attendance_session_id`, not `attendance_record_id`.
- Status/history responses include the daily summary and its ordered sessions.

## Initial mobile event body

Multipart form when selfie is attached, otherwise JSON:
- latitude, longitude, accuracy (required only when company policy requires location)
- selfie (required only when company policy requires selfie; JPEG/PNG; max 5MB)
- client_timestamp (optional client time, for diagnostics only; server time is authoritative)

## Storage

Selfies use the private local disk path under `tenants/{tenant}/attendance/selfies`. No public URLs.

## Important gaps before production

- Tenant timezone must be a configured tenant setting before date-boundary logic is considered final. The current implementation still uses the application timezone.
- Overnight sessions crossing the tenant's local date boundary need explicit handling and tests.
- Selfie validation, retention, and privacy policy must be confirmed.
- Overtime, break and correction approval workflows need completion and authorization tests.
- Fingerprint adapters need vendor/model-specific requirements and credentials strategy. Mobile OS biometric checks must remain on-device; raw biometric data must not be sent to Humexa.
- OpenAPI specification and integration tests remain to be added.
- Database migrations and all attendance workflows remain unexecuted/unverified in the local project environment.
