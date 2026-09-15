# Phase 3B — Beauty Salon Booking & Calendar

Salon appointments now connect clients, one stylist, one or more service lines, booking workflow, invoices, and recorded payments. The shared `/app/appointments` route chooses the correct implementation from business context.

## Architecture and reuse

The existing clinic appointment schema has required patient/doctor foreign keys and clinical status fields. The lower-risk approach is a separate `salon_appointments` table with shared booking primitives, rather than changing clinical records or cloning the clinic module.

- `BookingCore` supplies tenant serialization, appointment numbering, interval overlap, and conflict queries. Clinic appointment creation/status services now reuse its lock, creation reuses its number generator, and clinic availability reuses its conflict query.
- `tenants.appointment_sequence` supplies `APT-000001` numbering for both workflows under a transaction lock.
- `bookingLayout.js` supplies overlap-lane placement to both calendar implementations. Existing civil-date utilities, month calendar, appointment styling, application context, settings, permissions, and UI error components are reused.
- The new Pinia store loads salon booking data with request-generation checks and clears data when the booking view is replaced.
- One stylist handles all selected services in this release. Services are real relational line items, each with booked name, duration, price, amount, and deposit requirement snapshots.

## Availability and workflow

The server validates active clients in the selected location, active stylist accounts/memberships, location access, active service categories/services, and every selected service-to-stylist assignment. It calculates duration and money from service snapshots; posted totals, durations, patient IDs, doctor IDs, and status overrides are rejected.

Availability includes location opening hours and breaks, stylist weekly schedules and breaks, time off across locations, existing appointments, appointment buffers, and the appointment being edited. Missing opening hours or schedules produce no availability. Appointment limits are enforced per business/month.

Booking writes, status changes, schedules, time off, invoice creation, and payments serialize through the business row. SQLite takes a write lock before reads; row-locking engines use `lockForUpdate()`. Writes use retryable transactions. A separate-process test forces two workers to contend for the same stylist/time, verifying one booking and one conflict.

Overbooking defaults off. It requires both `salon.allow_overbooking` and `appointments.override_conflict`, plus a reason. It overrides stylist overlap only; client conflicts, hours, breaks, and time off remain enforced.

Statuses are Scheduled, Confirmed, Waiting, Checked In, In Service, Completed, Cancelled, and No Show. Check-in, service start, completion, and cancellation timestamps are persisted. Cancellation retains the appointment and requires a reason. Terminal/in-service appointments cannot be edited. Rescheduling revalidates availability and cannot modify notes or discounts through rescheduling-only permission.

## Calendar and profiles

- Desktop defaults to Week; mobile defaults to Day.
- Views: Day, Week, Month, Schedule, and Stylist columns.
- Filters: location, stylist, service, status, and backend search by appointment number/client name/client phone/stylist/service.
- Month cells show booking counts and open Day view. The Today panel shows filtered appointments and status counts; it collapses on smaller screens.
- Booking forms search existing clients, link to the existing client-creation workflow, select multiple services, filter eligible/available stylists, offer available slots, and calculate displayed duration and price. Walk-ins default to the current local date/time and Waiting.
- Client profiles/listing show real last-visit information; history includes service, stylist, status, and amount. Profile history respects booking permissions.
- Stylist profiles include today's/upcoming appointments, history, weekly schedules, and time off. Service profiles include upcoming appointments and booking count.
- Location hours are available through shared Settings > Locations and `/app/appointments/hours`.

## Shared billing and revenue

Inspection found a Billing settings page but no invoice/payment ledger. This phase adds one generic ledger (`billing_invoices`, `billing_invoice_items`, `billing_payments`) and a shared invoice/payment UI. The clinic billing landing page retains its existing settings behavior; the ledger is reusable through `/app/billing/invoices`.

Completing a salon appointment enables **Create Invoice**. The invoice uses the original service snapshots, currency, fixed discount, and tax. Repeated invoice creation returns the existing invoice. Invoice numbers use a locked business invoice sequence and existing billing prefix/length settings. Invoice plan limits apply.

Recorded payments respect configured payment methods, partial-payment policy, positive balances, and payment permissions. Idempotency keys prevent repeated payment requests from recording money twice. Amounts are calculated in minor units before persistence. This records payments received by staff; it does not initiate a gateway charge.

Deposit requirements are snapshotted and displayed with the salon deposit policy. Advance deposit collection and online gateway checkout are not part of this release; the completed-appointment invoice/payment flow is implemented.

Salon dashboard widgets are Total Clients, Today's Appointments, Active Stylists, and Monthly Revenue. Revenue is the actual payment ledger total for the current location/month and business currency, never an estimate from bookings. Permission/plan restrictions still suppress unavailable data.

## API and permissions

Internal booking endpoints use `/api/v1/salon/appointments`, with `options`, `clients`, `available-slots`, `available-stylists`, detail, update, reschedule, activity, workflow actions, and invoice creation. Calendars use the list endpoint with a bounded date range and pagination.

Schedule endpoints use `/api/v1/salon/location-hours` and `/api/v1/salon/stylists/{id}/schedule` / `time-off`. Profile histories use `/api/v1/salon/booking-history/{kind}/{id}`. Shared invoices/payments use `/api/v1/billing/invoices`.

Existing appointment permissions are reused, with `appointments.start_service`, `appointments.override_conflict`, and `appointments.discount` added. Stylist schedule/time-off changes require `salon_staff.manage`; location hours require location/settings management. Invoice creation and payment recording require `billing.create` and `billing.payments` respectively. Clinical APIs continue to reject salon access, and stadium does not receive this booking workflow.

## Migration

`2026_09_15_100000_create_salon_bookings_and_shared_billing.php` adds salon appointments, service lines, location hours, schedules, time off, shared billing tables, and `tenants.billing_invoice_sequence`. Tenant-qualified foreign keys protect appointment references and invoice/payment relationships. Supporting unique tenant/ID indexes are added to salon reference tables.

The migration is applied locally. A before/after comparison verified existing records were unchanged: 3 tenants, 4 clinic appointments, 2 patients, and 2 doctors. Existing salon client/stylist/service tables were empty and remain intact. Other environments need the migration before using the new code.

## Files created

- `app/Services/BookingCore.php`, `SalonBookingAvailability.php`, `SalonBookingService.php`, `BillingService.php`.
- `app/Http/Controllers/SalonBookingController.php`, `SalonScheduleController.php`, `BillingController.php`; `app/Http/Resources/SalonAppointmentResource.php`.
- `app/Models/SalonAppointment.php`, `SalonAppointmentService.php`, `SalonStaffSchedule.php`, `SalonStaffTimeOff.php`, `BillingInvoice.php`, `BillingInvoiceItem.php`, `BillingPayment.php`.
- `config/salon_booking.php`; the migration listed above.
- `resources/js/pages/appointments/BusinessAppointments.vue`; `resources/js/pages/salon/bookings/{Index,Form,Hours}.vue`; `resources/js/pages/billing/{BusinessBilling,Ledger}.vue`.
- `resources/js/components/appointments/BookingTimeline.vue`; `resources/js/components/salon/{BookingHistory,ScheduleEditor}.vue`.
- `resources/js/services/salonBookings.js`, `resources/js/stores/salonBookings.js`, `resources/js/utils/bookingLayout.js`.
- `tests/Feature/SalonBookingTest.php`, `SalonBookingConcurrencyTest.php`; `tests/Support/salon-booking-fixture.php`, `salon-booking-worker.php`; `tests/Browser/salon-booking.spec.js`, `salon-booking-fixtures.php`.
- `docs/phase-3b.md`.

## Files modified

- Clinic booking services: `AppointmentService.php`, `AppointmentStatusService.php`, `AppointmentAvailabilityService.php`.
- Access/settings/dashboard services: `ClinicAccessService.php`, `ClinicSettingsService.php`, `DashboardProfileService.php`.
- Salon models: `SalonClient.php`, `SalonStaffProfile.php`, `SalonService.php`; `SalonRecordResource.php`.
- `config/business_types.php`, `config/clinic.php`, `config/clinic_settings.php`, `config/dashboard.php`, `routes/api.php`.
- `resources/js/router/index.js`, `components/appointments/AppointmentTimeline.vue`, `components/settings/BranchSettings.vue`, `pages/salon/{Index,Show}.vue`, `config/salonPages.js`.
- `tests/Feature/BusinessModuleAccessTest.php` updates expectations for the newly implemented salon capabilities while retaining stadium/clinical enforcement checks.

## Verification

Tests cover booking creation, multiple services, duration, price snapshots, lifecycle, cancellation, rescheduling, client/stylist/service validation, assignment checks, location access, hours, breaks, time off, buffers, overbooking policy, discounts, no-show timing, search, calendar filtering, invoice/payment idempotency, permissions, tenant isolation, and composite foreign-key enforcement. Existing clinic appointment tests run in the full suite.

- `php artisan test --compact`: **112 passed, 2,133 assertions**. Includes clinic appointment regression, salon concurrency, billing/permissions, and tenant isolation.
- Clinic appointment tests also passed independently: **9 tests, 274 assertions**.
- `npx playwright test tests/Browser/salon-booking.spec.js`: **1 passed**. Verified all calendar views, multi-service duration/price, rescheduling/cancellation, walk-in lifecycle, invoice/payment, client history, stylist schedule save, revenue, and mobile layout. No page errors or Vue warnings.
- `npm run build`: **passed**, 271 modules; main JavaScript approximately 478 kB before gzip, with no large-chunk warning.
- `git diff --check`: passed.
- Local migration: applied; existing records verified unchanged as listed above.

Concurrency was executed against isolated SQLite databases with separate PHP workers. The row-locking implementation for other database engines was not integration-tested against a running MySQL/PostgreSQL server in this workspace. No stadium implementation was started.
