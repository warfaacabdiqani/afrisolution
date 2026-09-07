# Clinic application foundation

Signing in with a selected clinic opens `/app/dashboard` directly. Existing `/app/clinics` bookmarks also enter the dashboard when a clinic is selected. Explicit Switch Clinic links use `/app/clinics?switch=1` to show the chooser; selecting a clinic opens its dashboard. Platform administration remains under `/app/admin/*`.

## Added files

- `config/clinic.php`: clinic module catalog, feature mapping, and default role permissions.
- `app/Services/ClinicAccessService.php`: membership, permission, subscription, and branch access resolution.
- `app/Http/Controllers/ClinicDashboardController.php`: context, branch selection, dashboard, and future-module access endpoints.
- `database/migrations/2026_09_07_050000_add_clinic_access.php`: optional membership permission overrides, all-branch access flag, and branch assignments with composite tenant foreign keys.
- `resources/js/layouts/ClinicLayout.vue` and `resources/css/clinic.css`: independent responsive clinic shell.
- `resources/js/pages/clinic/Dashboard.vue`: clinic dashboard with loading, error, and empty states.
- `resources/js/pages/clinic/ModuleAccess.vue`: shared unavailable/access screen; no clinical CRUD implementation.
- `resources/js/components/clinic/AppointmentCalendar.vue`: navigable calendar using the clinic's local date.
- `resources/js/stores/clinicContext.js` and `clinicDashboard.js`: context and dashboard Pinia stores.
- `resources/js/config/clinicRoles.js`: clinic roles in the existing platform membership controls.
- `tests/Feature/ClinicDashboardTest.php`: clinic authorization and isolation coverage.

## Modified areas

The router and root app mount the clinic layout separately from platform administration. Clinic selection enters the dashboard. Session selection clears branch state and audits clinic entry. Existing clinic branch APIs now filter authorized branches. Platform member requests, provisioning, resources, and member controls support clinic roles and branch assignments. Provisioning also enforces doctor limits alongside existing member and branch limits. The authentication middleware redirects non-JSON guests to the existing SPA login route. Browser coverage includes the clinic dashboard.

## Routes and APIs

Clinic UI routes include dashboard, patients (list/create/detail), appointments (list/create/detail), doctors, consultations, prescriptions (list/create), pharmacy, billing, invoice creation, reports, staff, settings, support, and access restriction. Unimplemented workflows share a clearly identified availability screen.

| Method | API | Purpose |
| --- | --- | --- |
| GET | `/api/v1/clinic/context` | Current authorized clinic, branch, role, permission set, subscription, features, limits, and navigation |
| POST | `/api/v1/clinic/branch` | Select an authorized `branch_id`; reject tenant ownership input |
| GET | `/api/v1/clinic/dashboard` | Branch-scoped doctor/staff counts and empty clinical datasets |
| GET | `/api/v1/clinic/modules/{module}` | Server-authorized availability; `?action=create` checks creation permission |

## Authorization

Existing `auth:sanctum`, `platform.available`, and `tenant` middleware are reused. Clinic context can be read by a current active member even when the subscription is restricted, so the UI can explain the restriction. Operations require an active clinic, valid active/trial subscription, authorized branch, module permission, and enabled feature. Vue guards perform equivalent checks; backend checks remain authoritative. Clinic roles never grant platform permissions.

Permissions include `dashboard.view`, each module's `.view`, patient and appointment create/update permissions, appointment cancellation, consultation create/update, prescription creation, pharmacy management, billing creation/payments, staff management, and clinic settings view/update. Exact mappings live in `config/clinic.php`. Null membership permissions use role defaults; an explicit empty array denies all. Overrides can be managed through the existing authorized platform membership API.

Tenant ownership comes exclusively from the authenticated session and a fresh membership check. Branch assignments are constrained by both tenant and membership, with composite foreign keys preventing cross-tenant assignments. Existing memberships retain their previous access to all clinic branches; administrators can restrict them in Platform Administration → Clinic → Members. With `multi_branch` disabled, only the clinic's original branch is available. No unauthorized branch silently becomes selectable. Stale dashboard requests use clinic and branch context headers and receive 409 when their context changes.

Clinic entry and branch changes use the existing audit service; branch IDs are metadata, with no medical records or credentials included.

## Data and scope

Doctor and staff counts are database aggregates filtered by tenant, active membership/user, and branch assignment. Patient, appointment, prescription, and financial tables do not exist at this stage. Their datasets remain empty and their available KPI totals are zero; no sample records or percentages are inserted. Restricted KPIs show an unavailable marker. The visit chart displays a neutral empty ring, not an invented distribution.

The current tenant/branch schema has no clinic phone, email, or postal address fields. The dashboard shows only stored clinic details. Settings and staff workflow implementation remains a future stage. Patient/storage/appointment/invoice limits are exposed for those future write paths; this stage does not claim to enforce limits for writes that do not exist. Existing branch/member limits and the newly supported doctor role limit are enforced server-side.

## Verification

Use PHP 8.4 (`C:/php84/php.exe` in this workspace); the default terminal PHP 8.2 cannot run the installed Composer dependencies.

```powershell
& C:/php84/php.exe artisan migrate --force
& C:/php84/php.exe artisan test
npm run build
npm run test:browser
```

Browser tests use the dedicated test database, verify platform administration still works, select a clinic, check real empty states, refresh and navigate back, reject disabled pharmacy access, check responsive widths, and capture desktop/mobile screenshots under `test-results/`. Clinical CRUD remains intentionally deferred.
