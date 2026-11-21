# Humexa Attendance Module

API-first attendance implementation roadmap and initial backend contract.

## Current API v1 (tenant domain)

- GET /api/v1/attendance/policy
- GET /api/v1/attendance/status
- GET /api/v1/attendance/history
- POST /api/v1/attendance/check-in
- POST /api/v1/attendance/check-out

All endpoints use Sanctum authentication and domain-based tenancy. Employee self-service endpoints resolve the employee from the authenticated tenant user; they do not accept employee or tenant IDs from the client.

## Initial mobile event body

Multipart form when selfie is attached, otherwise JSON:
- latitude, longitude, accuracy (required only when company policy requires location)
- selfie (required only when policy requires selfie; JPEG/PNG; max 5MB)
- client_timestamp (optional client time, for diagnostics only; server time is authoritative)

## Storage

Selfies use the private local disk path under tenants/{tenant}/attendance/selfies. No public URLs.

## Important gaps before production

- Tenant timezone must be a configured tenant setting before date-boundary logic is considered final.
- Mobile API auth/token issuance and token revocation UX must be implemented/reviewed.
- Selfie validation, retention, and privacy policy must be confirmed.
- Overtime, break and correction workflows need approval policy design.
- Fingerprint adapters need vendor/model-specific requirements and credentials strategy.
- OpenAPI specification and integration tests remain to be added.
