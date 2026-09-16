# Phase 3C.2 — Clinic Billing Adapter

Implemented and verified on 2026-09-16. Phase 3C.1 was extended, not redesigned. No new database migration is required.

## Workflow and architecture

`POST /api/v1/clinic/appointments/{appointment}/invoice` authorizes the existing Clinic appointment module and billing creation, then calls the existing `BillingService::createFromSource()` with the fixed `clinic_appointment` alias.

The new `ClinicAppointmentBillingAdapter` implements the existing `InvoiceSource` contract. It uses `AppointmentService::find()` to preserve authorized-branch and assigned-doctor visibility. It requires the actual existing `completed` lifecycle status; scheduled, confirmed, waiting, checked-in, in-consultation, cancelled, and no-show records are not billable.

The adapter resolves the appointment's patient, doctor, branch, and stored billing settings. It supplies one consultation charge to the unchanged shared financial core. Numbering, monthly limits, invoice/item persistence, monetary calculation, balances, payments, transaction locks, and audit infrastructure remain shared with Salon billing.

Completing a consultation does not automatically create financial records. The appointment drawer offers **Create Invoice** only for completed appointments with billing access and creation permission. Once an invoice exists, it offers **View Invoice** instead. The same drawer is reachable from the consultation and patient appointment screens.

## Fee, ownership, and snapshots

- Use the doctor's consultation fee when it is non-null, including a valid zero fee.
- Otherwise use the Clinic's default `billing.consultation_fee`.
- Quantity is one; description is `Consultation - Dr. {name}`.
- There is no appointment discount field in the existing Clinic workflow, so the discount is zero.
- The shared calculator applies the configured billing tax rate to the fee and snapshots rate, tax amount, and final total.
- Currency comes from the resolved billing settings at issuance, including their existing general-currency fallback.
- The patient is the customer: invoices receive `patient_id`, retain `customer_name`, and expose `{type: "patient", id, name}`. `salon_client_id` remains null.
- The source and line both use the allowlisted `clinic_appointment` identity. Frontend-supplied customer, tenant, branch, source, prices/items, and totals cannot replace server-resolved values.
- The invoice branch is the appointment branch. Existing Clinic patients are tenant-wide: their registration branch does not have to equal the branch where treatment occurred. This preserves the existing appointment architecture while keeping invoice branch authorization enforced.

Issued invoices are read from their saved financial snapshots. Changing doctor/default fees, patient or doctor names, billing currency, or tax settings does not change existing invoices. New eligible appointments use the new settings. No prescriptions or medicines are added.

## Idempotency and access

The existing tenant lock and unique `(tenant_id, source_type, source_id)` constraint protect against duplicate invoices. Repeated generation returns the same invoice and number, including when the invoice limit has been reached. A two-process test verifies concurrent Clinic issuance produces one invoice, one charge line, and one sequence increment.

Creation requires all of:

- An active authenticated tenant context and subscription.
- The existing appointment capability/plan feature and `appointments.view`.
- Access to the particular source appointment through authorized branches and existing doctor visibility rules.
- A healthcare/clinical business capability.
- Billing business capability and the existing billing plan feature.
- `billing.view` and `billing.create`.

No new broad clinical permissions or appointment-management permissions are granted. Assigned clinicians can invoice with appointment-view and billing rights; users needing access to other clinicians' appointments still need the existing `appointments.view_all` permission. Salon businesses cannot use this Clinic endpoint. Salon's own billing continues with `clinical=false`.

The shared creation audit includes business type, source, appointment ID, patient ID, and branch ID, without diagnoses, notes, or other medical details.

## Patient history and shared ledger

The Patient profile Billing placeholder now renders the same `Ledger.vue` used by the main Clinic and Salon Billing pages. It requests:

`GET /api/v1/billing/invoices?customer_type=patient&customer_id={patient}`

The index request validates the filter pair and customer alias. The customer resolver verifies business compatibility and tenant ownership; the query then filters invoices server-side while retaining the existing authorized-branch scope and pagination. No client-side filtering of all invoices is used.

The table includes invoice number/link, issue date, source label, customer, branch, total, paid, balance, and status. Patient profile IDs are explicitly treated as customer filters, never invoice IDs. Changing the selected patient invalidates old requests and reloads history; business/branch switching protections remain in place.

Clinic and Salon payments both use `POST /api/v1/billing/invoices/{invoice}/payments`. No Clinic payment implementation was added. Partial/full payment, payment-method validation, overpayment rejection, idempotency, and audit behavior are unchanged.

## Files created for this phase

- `app/Services/Billing/ClinicAppointmentBillingAdapter.php`
- `app/Http/Requests/BillingInvoiceIndexRequest.php`
- `resources/js/pages/patients/tabs/Billing.vue`
- `tests/Feature/ClinicBillingTest.php`
- `tests/Feature/ClinicBillingConcurrencyTest.php`
- `tests/Support/clinic-billing-fixture.php`
- `tests/Support/clinic-billing-worker.php`
- `tests/Browser/clinic-billing.spec.js`
- `docs/phase-3c2-clinic-billing.md`

## Files modified for this phase

- `app/Http/Controllers/BillingController.php`
- `app/Http/Resources/AppointmentResource.php`
- `app/Models/Appointment.php`
- `app/Services/AppointmentService.php`
- `app/Services/Billing/BillingCustomerResolver.php`
- `config/billing.php`
- `routes/api.php`
- `resources/js/components/appointments/AppointmentDrawer.vue`
- `resources/js/pages/billing/Ledger.vue`
- `resources/js/pages/patients/Show.vue`
- `resources/js/router/index.js`
- `resources/js/composables/useBillingLedger.js`
- `resources/js/services/appointments.js`
- `resources/js/services/billing.js`
- `playwright.billing.config.js`
- `tests/Feature/SharedBillingConcurrencyTest.php`
- `tests/Frontend/billing-context.test.mjs`

The shared concurrency test's worker path and expected item count were made overridable so the Clinic test can reuse the proven test harness without changing the Salon scenario.

## Verification

Commands used PHP 8.4 from `C:/php84/php.exe` because the shell's default PHP is an older installation.

- `php artisan test --filter=ClinicBillingTest`: **10 passed, 138 assertions**.
- `php artisan test`: **135 passed, 2,401 assertions**. Includes Clinic and Salon invoice concurrency, shared billing, SalonBookingTest, appointment, patient, permissions, tenancy, and other existing regressions.
- `node --experimental-vm-modules --test tests/Frontend/billing-context.test.mjs`: **4 passed**. Includes customer-filter changes, late responses, business/branch switching, and payment-key lifetime.
- `npx playwright test --config=playwright.billing.config.js`: **6 passed**. Covers explicit consultation completion/issuance, Create → View action replacement, patient history query, non-billable states, and Clinic/Salon shared ledger/payment UI.
- `npm run build`: **passed**, final build without the initial chunk-import warning.
- `php artisan route:list --path=clinic/appointments`: new invoice endpoint registered; all existing appointment routes remain.
- `git diff --check`: passed. Required new files are included in the Git diff; no required implementation files remain untracked.

Browser tests use the real built Vue application with intercepted API responses; PHP tests independently exercise the real endpoints, database relationships, and shared services. Concurrency tests use dedicated temporary SQLite databases. No local development financial data was rewritten.

## Remaining scope

No known failing Phase 3C.2 checks remain. Receipts, medicine/pharmacy billing, procedure catalogs, inventory, products, POS, stock, gateways, and financial reports were not implemented. Phase 3C.3 has not started.
