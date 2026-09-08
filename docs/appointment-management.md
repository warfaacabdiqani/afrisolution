# Appointment Management

The earlier appointment backend files were unfinished and had no registered APIs or connected Vue screens. This stage completes their integration and replaces the `/app/appointments` placeholder.

## Calendar and workflow

- Week and month calendars start on **Saturday** and end on **Friday**. The dashboard mini-calendar and clinician schedule display use the same ordering; stored schedule weekdays remain ISO 1–7.
- Desktop defaults to Week, with a central timeline and Today panel. Day, Month, and paginated Schedule views share the same API/data store. Mobile defaults to Day, with a collapsible Today panel and card-based list view.
- Dates, filters, selected view and pagination are route-driven. Appointment details use a route-addressable drawer; creation, editing and rescheduling have dedicated pages.
- Filters cover authorized branch, active clinician, status, visit type, search and list date range. Search matches appointment references, patient names/numbers/phones and clinician names.
- Timeline cards use centralized status colors and concurrent appointments for different clinicians occupy separate lanes. Working hours, breaks, leave, current day and current time are represented. Clicking a working slot opens a prefilled booking form.
- Patient selection uses minimal demographic results. Inline registration reuses the existing PatientForm and Laravel patient APIs, preserving duplicate detection and patient permissions.
- Appointment types are stored per clinic with a default duration and can be added by authorized staff.

## Data and migration

`database/migrations/2026_09_07_090000_create_appointment_management.php` adds:

- `tenants.appointment_sequence` for transaction-safe references such as `APT-000001`.
- `appointment_types`: clinic-specific visit names, duration, color key and status.
- `appointments`: tenant, branch, patient, clinician and type references; start/end; source; walk-in flag; clinical workflow status; cancellation details; actor IDs; and workflow timestamps.

Composite foreign keys enforce tenant ownership for referenced records. Indexes support tenant/branch/doctor/patient/status date queries. The migration is applied to the local application's **MySQL** database. No sample appointments were inserted there.

Appointment belongs to Patient, Doctor, Branch, AppointmentType and its creator. Patient and Doctor expose appointment relationships. Appointment and AppointmentType reuse the existing fail-closed tenant model scope.

`starts_at` and `ends_at` store clinic-local civil datetimes, matching the existing local weekly schedule and leave conventions. Workflow/audit timestamps use application UTC. API consumers should interpret scheduling dates in the clinic timezone, not the browser timezone.

## API endpoints

All routes have prefix `/api/v1/clinic` and use the existing session authentication, active tenant, subscription, feature and branch checks.

| Method | Route | Purpose |
| --- | --- | --- |
| GET, POST | `/appointments` | Paginated list or create |
| GET | `/appointments/calendar` | Date-range events and daily counts |
| GET | `/appointments/today` | Today's events and status counts |
| GET | `/appointments/options` | Accessible active clinicians, types, status/action definitions |
| GET | `/appointments/patients` | Active patient search/selection |
| GET | `/appointments/hours` | Working intervals and leave without private reasons |
| GET, PUT | `/appointments/{appointment}` | Detail or edit |
| POST | `/appointments/{appointment}/reschedule` | Fully revalidated scheduling change |
| POST | `/appointments/{appointment}/{action}` | Confirm, check-in, start-consultation, complete, cancel, no-show |
| GET | `/appointments/{appointment}/activity` | Paginated audit activity |
| GET | `/doctors/{doctor}/available-slots` | Valid slots for branch/date/duration/patient |
| GET, POST | `/appointment-types` | List or create clinic visit types |

Calendar requests require a bounded date range (at most 42 days). Events are paginated at 200 and the client fetches remaining pages for the selected period; monthly counts cover the entire query. Schedule and Today lists use 25 rows per page. Eager loading avoids per-event relationship queries. Slot generation preloads schedules, leave and conflicting bookings rather than querying for every candidate time.

## Availability and conflicts

Booking requires an active patient from this tenant and an active clinician assigned to the authorized branch. It validates the full interval against working hours, breaks, clinician availability, dated leave, doctor conflicts and patient conflicts. Doctor/patient conflict checks span branches in the same tenant and do not expose the conflicting record's identity.

The slots API preserves its time-array response and includes diagnostic metadata. Missing branch schedules, non-working weekdays, clinician unavailability, dated leave, past dates, exhausted working hours and occupied/too-short intervals have distinct messages. For missing schedules the booking form links to the correct clinician/branch Schedule tab in a new tab, preserving the draft appointment. After saving actual working days/hours, **Refresh available times** reloads the slots. Being marked Available does not implicitly create a working schedule; no clinic hours are invented or silently saved.

The overlap condition is `existing.starts_at < proposed.ends_at AND existing.ends_at > proposed.starts_at`. Adjacent appointments are allowed; identical and partially overlapping bookings are rejected. Cancelled/no-show appointments release time, while completed records retain their historical interval. Rescheduling excludes only the current accessible appointment and reruns validation.

Creation, rescheduling and status changes take the tenant row lock within a transaction. This serializes cooperating application writes on MySQL, protecting overlap checks, sequence allocation and quota checks. Existing schedule and leave changes use the same tenant lock. Slots are advisory; submitting an appointment always revalidates it.

`appointments.override_schedule` can bypass working hours/breaks only with a recorded reason. It cannot bypass leave, inactive clinicians, patient/doctor conflicts, ownership, branch access, or plan limits. Normal bookings must be in the future; walk-ins may use an earlier time on the current clinic-local day. Overnight appointments are rejected.

## Status workflow

`config/appointments.php` centralizes labels, colors, permitted transitions and timestamp fields.

| Action | From | To |
| --- | --- | --- |
| Confirm | Scheduled | Confirmed |
| Check In | Scheduled, Confirmed, Waiting | Checked In |
| Start Consultation | Checked In | In Consultation |
| Complete | In Consultation | Completed |
| Cancel (reason required) | Scheduled, Confirmed, Waiting, Checked In | Cancelled |
| No Show | Scheduled, Confirmed | No Show |

Walk-ins default to Waiting. Check-in, consultation start and completion record timestamps; cancellation records actor, time and reason. Future-day check-in/start/completion and premature no-show are rejected. Completed/cancelled/no-show records cannot be edited or rescheduled. Rescheduling an editable appointment resets its scheduling status and check-in timestamp, and preserves prior timing/clinician/branch references in audit history. There is no hard-delete endpoint.

## Permissions, tenancy and limits

Permissions are registered in `config/clinic.php` and the membership permission allowlist:

`appointments.view`, `view_all`, `create`, `update`, `reschedule`, `cancel`, `check_in`, `start_consultation`, `complete`, `override_schedule`, and `types.manage` (all with the `appointments.` prefix).

Owner/admin wildcard access is retained. Receptionists have booking and reception actions across accessible branches; nurses have view/check-in support. Doctors see only appointments assigned to their linked clinician profile unless they additionally hold `appointments.view_all`. Explicit membership permission overrides are preserved. Hiding UI controls is supplemented by server authorization for every action.

Patient records remain clinic-wide, as in Patient Management; appointment records are branch-scoped. Tenant IDs and generated references cannot be assigned from request data. Foreign tenant/branch IDs are rejected, and existing hidden appointments cannot be viewed or mutated by changing a URL.

The plan's `appointment_limit` is enforced per clinic-local calendar month of scheduled start, across all branches and retained statuses. Cancelled appointments still count toward the limit. Moving to another month checks that month's usage. Permission and quota checks apply to direct API requests.

## Integrations and audit

The dashboard uses real today counts, appointment rows and monthly date markers. Patient and clinician Appointments tabs use the same scoped list API and offer booking when authorized.

Audit events cover created, updated, rescheduled, confirmed, checked_in, consultation_started, completed, cancelled and no_show. Metadata includes actor, tenant, branch, appointment, patient and clinician references, plus prior scheduling values where relevant. Medical notes and cancellation reasons are not copied into audit metadata.

`AppointmentChanged` dispatches after commit with tenant/appointment identifiers and the action. It is the integration point for future queued notifications/reminders; no SMS, WhatsApp or email delivery is claimed or triggered by this stage.

## Files

Backend added/completed:

- `app/Events/AppointmentChanged.php`
- `app/Models/{Appointment,AppointmentType}.php`
- `app/Http/Controllers/{AppointmentController,AppointmentCalendarController,AppointmentAvailabilityController}.php`
- `app/Http/Requests/AppointmentRequest.php`, `app/Http/Resources/AppointmentResource.php`
- `app/Services/{AppointmentService,AppointmentAvailabilityService,AppointmentStatusService}.php`
- `config/appointments.php` and the appointment migration above.

Frontend added:

- `resources/js/pages/appointments/{Index,FormPage}.vue`
- `resources/js/components/appointments/{AppointmentTimeline,AppointmentMonthView,AppointmentScheduleView,AppointmentStatusBadge,TodayPanel,AppointmentDrawer,AppointmentForm,ProfileAppointments}.vue`
- `resources/js/pages/patients/tabs/Appointments.vue`
- `resources/js/services/appointments.js`, `resources/js/stores/appointments.js`, `resources/js/utils/appointmentDates.js`
- `resources/css/appointments.css`

Existing files modified: `routes/api.php`, `config/clinic.php`, MemberRequest, ClinicDashboardController, Patient and Doctor models, Vue Router, CSS imports, PatientForm's reusable embedded mode, patient profile booking link, clinic Dashboard and mini-calendar, and clinician Schedule/Appointments tabs.

Tests: `tests/Feature/AppointmentManagementTest.php`, `tests/Browser/z_appointments.spec.js`; the existing `tests/Browser/saas.spec.js` mini-calendar check now expects the Day-view URL.

## Verification and scope

- Backend suite: 49 tests / 782 assertions passed.
- Production Vite build passes (178 modules). Live appointment routes return HTTP 200 and reference the updated assets.
- Browser validation covers calendar ordering, schedule setup from the empty-slots prompt, all four views, filters, registration, booking, rescheduling, cancellation, walk-in progression through completion, profile/dashboard integration, mobile layout, refresh and history navigation.
- All three Playwright workflows passed: clinic/patient administration, clinician management, and appointment management. No Vue page errors were recorded. Desktop/mobile appointment screenshots were visually checked and retained under `storage/framework/testing/appointments-{week,mobile}.png`.
- Drag-and-drop, doctor-column mode, outbound reminders and full Consultation/EMR are intentionally outside this completed core stage. Rescheduling uses the validated dedicated form; starting a consultation changes appointment status only.
