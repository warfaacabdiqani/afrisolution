# Doctors / Clinicians Management

The clinic's `/app/doctors` placeholder is replaced with a responsive directory, real branch statistics, search, specialty/status/availability/branch filters, pagination, and dedicated create, edit, and profile screens. Profile tabs show overview, weekly schedule and leave, activity, and an explicit unavailable Appointments integration tab. No sample clinicians are inserted into the application database.

## Files created

- `database/migrations/2026_09_07_080000_create_clinician_management.php`
- `app/Models/{Doctor,Specialty,DoctorSchedule,DoctorLeave}.php`
- `app/Services/DoctorService.php`
- `app/Http/Requests/DoctorRequest.php`
- `app/Http/Resources/DoctorResource.php`
- `app/Http/Controllers/{DoctorController,DoctorScheduleController,DoctorLeaveController}.php`
- `resources/js/services/doctors.js` and `resources/js/stores/doctors.js`
- `resources/js/components/doctors/{DoctorForm,DoctorTable,DoctorBadge,DoctorActions,DoctorLeavePanel}.vue`
- `resources/js/pages/doctors/{Index,FormPage,Show}.vue`
- `resources/js/pages/doctors/tabs/{Overview,Schedule,Appointments,Activity}.vue`
- `resources/css/doctors.css`
- `tests/Feature/DoctorManagementTest.php`
- This report.

## Existing files modified

- `routes/api.php`, `resources/js/router/index.js`: API and page routes; remove the clinician placeholder, retain route permission checks and list filter navigation.
- `config/clinic.php`, `app/Http/Requests/MemberRequest.php`: clinician permissions and role defaults.
- `app/Services/TenantProvisioningService.php`: share clinician quota accounting with membership provisioning.
- `app/Http/Controllers/ClinicDashboardController.php`: count active profiles plus unlinked legacy doctor memberships without double counting; expose module availability.
- `resources/css/app.css`: import clinician styles.
- `tests/Browser/saas.spec.js`: clinician browser workflow and desktop/mobile screenshots.

## Database and relationships

The applied migration adds `tenants.doctor_sequence`, `doctors`, `specialties`, `doctor_branch`, `doctor_specialty`, `doctor_schedules`, and `doctor_leaves`. All clinical records carry tenant ownership. Composite foreign keys enforce matching tenant ownership for branches, doctors, specialties, and linked memberships. Database uniqueness protects doctor numbers and account linkage per tenant.

Doctor belongs to an optional existing user, has multiple branches and specialties, and has schedules and leaves. `primary_branch_id` is also included in branch assignments. Codes such as `DOC-00001` are generated under a tenant row lock and remain stable. Profiles can be deactivated; there is no hard-delete endpoint.

## APIs

All routes below have prefix `/api/v1/clinic`, with authentication, active tenant/subscription checks, and clinician feature/permission enforcement.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/doctors/options` | Authorized branches, specialties, linkable users, permissions and quota |
| GET, POST | `/specialties` | List or create clinic specialties |
| GET, POST | `/doctors` | Filter/paginate real records or register a clinician |
| GET, PUT | `/doctors/{doctor}` | View or edit profile |
| POST | `/doctors/{doctor}/activate` or `/deactivate` | Change profile status |
| GET, PUT | `/doctors/{doctor}/schedule` | Read/replace a branch's weekly schedule |
| GET, POST | `/doctors/{doctor}/leaves` | List or record dated leave |
| POST | `/doctors/{doctor}/leaves/{leave}/cancel` | Retain and cancel leave |
| GET | `/doctors/{doctor}/activity` | Paginated audit activity |

## Permissions and security

Permissions: `doctors.view`, `doctors.create`, `doctors.update`, `doctors.deactivate`, `doctors.schedule.view`, `doctors.schedule.update`, `doctors.leave.manage`, and `doctors.specialties.manage`. Linking/creating a login additionally requires `staff.manage`. Existing administrator wildcards apply; doctor/receptionist read defaults include directory and schedule access. Explicit existing membership permission overrides are preserved.

Tenant ownership comes from the authenticated session and model scope, not posted IDs. Queries and writes validate tenant membership and authorized branches. Global profile edits, deactivation, and all-branch leave require access to every branch assigned to that clinician. Branch-specific schedules and leave require access to that assigned branch. Hidden branches are omitted from profile responses. List responses omit private profile notes and qualifications; leave reasons require leave-management permission.

Optional new accounts use hashed passwords, the clinic doctor role, and only assigned branches. Granted permissions must be in the doctor role allowlist and held by the actor. Existing accounts must already be active clinic members with access to all profile branches. Clinical profile status, user login status, and availability are separate.

## Quota

Creation is serialized with a tenant lock and checks the current plan's doctor limit. Usage includes retained active/inactive profiles plus active doctor memberships without a linked profile. Linking an existing doctor membership does not consume a second place. Deactivation retains the profile and its place. Account creation separately checks the subscription's member limit. Direct API requests use the same checks.

## Schedule and leave

Each branch schedule stores at most one interval and one optional break per weekday (ISO 1–7) in the clinic timezone. Save validates seven distinct day entries, chronological times, complete in-range breaks, and conflicting hours at other branches. Unavailable days are represented by absence of a working interval. Overnight shifts are not supported. Removing a branch requires clearing its schedule and cancelling upcoming branch leave first.

Leave has inclusive start/end dates, an optional reason, and either one assigned branch or all assigned branches. Overlapping noncancelled leave is rejected. Current, scheduled, completed and cancelled states are displayed from dates and cancellation state. Current leave overrides manual availability without changing account/profile status. `DoctorService::availableAt()` provides a tested integration point for future appointment checks using local dates, schedules, breaks, leave, and availability. No appointment booking or consultation engine is introduced.

## Audit

Events: `doctor.created`, `doctor.updated`, `doctor.activated`, `doctor.deactivated`, `doctor.branch.assigned`, `doctor.schedule.updated`, `doctor.leave.created`, and `doctor.leave.cancelled`. Metadata records doctor/branch identifiers. Passwords, profile notes and leave reasons are not added to audit metadata.

## Verification

- PHP 8.4: `artisan test` — 41 tests, 524 assertions passed.
- Vite: `npm run build` — passed, 164 modules transformed.
- Migration status confirms clinician migration applied to the local application database.
- Playwright: 2 end-to-end workflows passed. Checks cover registration, generated code, specialty creation, schedule, dated leave/cancellation, search, status changes, reload persistence, desktop row actions, mobile layout, and Vue console errors. The existing platform/patient regression workflow also passed.
- The live `http://127.0.0.1:8000/app/doctors` route returns HTTP 200 and references the new production assets.
- Appointment, consultation, prescription, pharmacy, and billing workflows remain outside this stage.
