# Shared Business Deposits Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Collect, receipt, refund or forfeit, and allocate deposits through Afriso's shared billing ledger for Clinic appointments, Dental treatment plans, and Beauty Salon bookings.

**Architecture:** Each supported workflow provides a registered deposit-source adapter. A shared `BillingDepositService` creates one deposit invoice, records normal shared-ledger payments, computes server-owned summaries, reverses payments during cancellation, and allocates the deposit invoice across final invoices. Stadium stays fail-closed until it has a real reservation source.

**Tech Stack:** Laravel 12, PHP 8.4, Eloquent, SQLite/MySQL-compatible migrations, Vue 3, Pinia, Vite, Playwright.

**Spec:** `docs/superpowers/specs/2026-09-30-business-deposits-design.md`

## Global Constraints

- Reuse `billing_invoices`, `billing_invoice_items`, `billing_payments`, and `billing_receipts`; do not create a second billing system.
- Preserve existing Clinic, Dental, Salon, and historical billing behavior when no deposit is configured.
- All financial values are calculated from server-owned source snapshots with integer-cent arithmetic.
- Every endpoint enforces tenant, branch, business capability, plan feature, and existing billing permissions.
- Deposit invoice, payment, receipt, refund, allocation, cancellation, and audit writes are atomic and idempotent.
- Stadium must reject deposit operations until a facility reservation source exists.
- Do not add an online payment gateway, wallet, stored card, or general credit-note feature.

## Review Focus

- A deposit percentage on a fractional charge must round once and reconcile across the deposit and final invoices.
- A partly paid deposit invoice must leave the correct combined balance after the final invoice is issued.
- Concurrent collection attempts must never collect above the deposit requirement or create duplicate receipts.
- A Dental deposit allocated over several procedure invoices must never be allocated twice or exceed either invoice.
- Cancellation retries must not duplicate refunds, refund receipts, voiding, forfeiture, or audit events.

---

### Task 1: Shared Deposit Ledger and Money Contracts

**Files:**
- Create: `database/migrations/2026_09_30_100000_add_shared_business_deposits.php`
- Create: `app/Models/BillingDepositAllocation.php`
- Create: `app/Services/Billing/DepositSource.php`
- Create: `app/Services/Billing/BillingDepositService.php`
- Create: `app/Http/Requests/BillingDepositPaymentRequest.php`
- Modify: `app/Models/BillingInvoice.php`
- Modify: `app/Models/BillingPayment.php`
- Modify: `app/Http/Resources/BillingInvoiceResource.php`
- Modify: `app/Http/Resources/BillingPaymentResource.php`
- Modify: `app/Services/BillingService.php`
- Modify: `config/billing.php`
- Test: `tests/Feature/BusinessDepositTest.php`

**Interfaces:**
- `DepositSource::snapshot(array $context, int $sourceId): array` returns tenant, branch, customer, currency, required amount, label, and source ownership.
- `BillingDepositService::collect(array $context, string $sourceType, int $sourceId, array $payment): BillingInvoice` creates/reuses the deposit invoice then delegates safe collection.
- `BillingDepositService::summary(array $context, string $sourceType, int $sourceId): array` returns required, invoiced, collected, refunded, remaining, status, invoice ID, and receipts.
- `BillingDepositService::allocate(BillingInvoice $finalInvoice, BillingInvoice $depositInvoice, int $maxCents): int` creates or returns an immutable allocation.
- `BillingDepositService::resolveCancellation(..., string $disposition, string $reason, string $idempotencyKey): void` voids, refunds, or forfeits atomically.
- `BillingService::refundDepositPayment(...)` creates a negative `refund` payment and immutable refund receipt linked through `reverses_payment_id`.
- `BillingMoney::signedCents(mixed $value): int` parses stored refunds while the existing `cents` method remains non-negative for charges and browser input.
- `BillingMoney::calculate(array $charges, mixed $discount, mixed $taxRate, mixed $postTaxCredit = '0.00')` calculates tax before credit, emits a `credit` document line, and returns a non-negative reconciled total.

- [ ] **Step 1: Write failing shared-ledger tests** for additive migration safety, exact deposit calculation/rounding, invoice reuse, full and partial collection, payment/refund idempotency conflicts, receipt snapshots, allocation uniqueness, over-collection, and tenant ownership.
- [ ] **Step 2: Run `php artisan test --filter=BusinessDepositTest`** and verify failures are caused by missing deposit schema and services.
- [ ] **Step 3: Add the migration and model relationships** with payment type default `payment`, nullable reversal self-reference, invoice `credit`, invoice-item `kind`, allocation uniqueness, indexed source lookups, and reversible foreign-key order.
- [ ] **Step 4: Implement the deposit contract and service** using `BillingLock`, `BillingMoney`, existing invoice/payment/receipt numbering, server snapshots, and transactions.
- [ ] **Step 5: Extend billing payment/refund resources and reports-compatible signed money handling** without changing existing positive-payment behavior.
- [ ] **Step 6: Run `php artisan test --filter=BusinessDepositTest`** and verify all Task 1 tests pass.
- [ ] **Step 7: Commit** with `feat: add shared deposit ledger foundation`.

### Task 2: Beauty Salon Deposit Collection and Cancellation

**Files:**
- Create: `app/Services/Billing/SalonDepositAdapter.php`
- Modify: `app/Models/SalonAppointment.php`
- Modify: `app/Services/SalonBookingService.php`
- Modify: `app/Http/Controllers/SalonBookingController.php`
- Modify: `app/Http/Resources/SalonAppointmentResource.php`
- Modify: `app/Services/Billing/SalonBillingAdapter.php`
- Modify: `routes/api.php`
- Modify: `config/billing.php`
- Test: `tests/Feature/SalonDepositTest.php`
- Test: `tests/Feature/SalonBookingTest.php`
- Test: `tests/Feature/SharedBillingTest.php`

**Interfaces:**
- `SalonDepositAdapter` implements `DepositSource` for `salon_appointment_deposit` from the booking's existing immutable `deposit_required` snapshot.
- `POST /api/v1/salon/appointments/{id}/deposit/payments` consumes `BillingDepositPaymentRequest`.
- Existing cancel action accepts `deposit_disposition` and `deposit_idempotency_key` only when collected funds exist.
- `SalonAppointmentResource` returns `deposit` from `BillingDepositService::summary`.

- [ ] **Step 1: Write failing Salon tests** for collection at scheduled/confirmed states, immediate receipt, partial collection policy, edit locks, rescheduling, final-invoice reduction, unpaid cancellation void, refund, forfeit, retries, authorization, and cross-tenant/branch IDs.
- [ ] **Step 2: Run focused Salon deposit tests** and verify expected missing-route/behavior failures.
- [ ] **Step 3: Implement `SalonDepositAdapter`, routes, controller methods, resource summary, and relationships** while preserving current service deposit snapshots.
- [ ] **Step 4: Integrate cancellation resolution and financial-field locks** while allowing authorized scheduling and notes changes.
- [ ] **Step 5: Update `SalonBillingAdapter`** to add the allocated deposit adjustment and exact expected totals without accepting client totals.
- [ ] **Step 6: Run `php artisan test --filter='SalonDepositTest|SalonBookingTest|SharedBillingTest'`** and verify the Salon and shared billing suites pass.
- [ ] **Step 7: Commit** with `feat: collect salon booking deposits`.

### Task 3: Clinic Appointment Deposit Configuration and Collection

**Files:**
- Create: `app/Services/Billing/ClinicAppointmentDepositAdapter.php`
- Modify: `app/Models/Appointment.php`
- Modify: `app/Models/AppointmentType.php`
- Modify: `app/Services/AppointmentService.php`
- Modify: `app/Http/Controllers/AppointmentController.php`
- Modify: `app/Http/Requests/AppointmentRequest.php`
- Modify: `app/Http/Resources/AppointmentResource.php`
- Modify: `app/Services/Billing/ClinicAppointmentBillingAdapter.php`
- Modify: `app/Services/ClinicSettingsService.php`
- Modify: `app/Http/Requests/ClinicSettingsRequest.php`
- Modify: `config/billing.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ClinicDepositTest.php`
- Test: `tests/Feature/AppointmentManagementTest.php`
- Test: `tests/Feature/ClinicSettingsTest.php`
- Test: `tests/Feature/ClinicBillingTest.php`

**Interfaces:**
- Appointment type accepts nullable `deposit_mode` and `deposit_value`; `null` mode means inherit clinic billing defaults.
- Appointment creation snapshots `deposit_mode`, `deposit_value`, `deposit_basis`, and `deposit_required` from the resolved consultation fee.
- `POST /api/v1/clinic/appointments/{id}/deposit/payments` collects through `ClinicAppointmentDepositAdapter`.
- Existing clinic cancellation accepts the same disposition contract as Salon.

- [ ] **Step 1: Write failing Clinic tests** for default and appointment-type fixed/percentage modes, doctor versus clinic fee basis, rounding/capping, zero deposits, collection/receipt, edit lock, reschedule, cancellation dispositions, final invoice reconciliation, permissions, and isolation.
- [ ] **Step 2: Run the focused Clinic tests** and verify failures demonstrate missing configuration and deposit behavior.
- [ ] **Step 3: Add validated settings and appointment-type fields**, snapshot the resolved requirement during booking creation, and expose server summaries.
- [ ] **Step 4: Add Clinic collection and cancellation endpoints** through the shared deposit service.
- [ ] **Step 5: Update the Clinic final adapter** to allocate the deposit without changing no-deposit invoices.
- [ ] **Step 6: Run `php artisan test --filter='ClinicDepositTest|AppointmentManagementTest|ClinicSettingsTest|ClinicBillingTest'`** and verify all pass.
- [ ] **Step 7: Commit** with `feat: collect clinic appointment deposits`.

### Task 4: Dental Plan Deposits and Multi-Invoice Allocation

**Files:**
- Create: `app/Services/Billing/DentalPlanDepositAdapter.php`
- Modify: `app/Models/DentalPlan.php`
- Modify: `app/Services/DentalPlanService.php`
- Modify: `app/Http/Controllers/DentalPlanController.php`
- Modify: `app/Services/Billing/DentalTreatmentBillingAdapter.php`
- Modify: `config/billing.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/DentalDepositTest.php`
- Test: `tests/Feature/DentalWorkflowTest.php`

**Interfaces:**
- Draft plan accepts `deposit_mode` and `deposit_value`; acceptance snapshots and freezes `deposit_basis` and `deposit_required`.
- `POST /api/v1/dental/plans/{plan}/deposit/payments` collects against `dental_plan_deposit`.
- Treatment invoice creation calls allocation with the plan's remaining unallocated deposit.
- Plan cancellation uses refund/forfeit disposition when collected funds exist.

- [ ] **Step 1: Write failing Dental tests** for fixed/percentage validation, acceptance snapshot, post-acceptance locks, collection, receipt, partial payment, sequential allocation over differently priced procedures, concurrent/retried invoicing, cancellation resolution, permissions, and isolation.
- [ ] **Step 2: Run focused Dental tests** and verify the failures reflect missing plan deposit behavior.
- [ ] **Step 3: Implement plan configuration, acceptance snapshot, adapter, collection endpoint, and resource summary** using existing patient and branch ownership.
- [ ] **Step 4: Implement deterministic multi-invoice allocation** under billing/source locks and add the negative adjustment to each treatment invoice.
- [ ] **Step 5: Integrate cancellation refund/forfeit and preserve completed treatment invoices**.
- [ ] **Step 6: Run `php artisan test --filter='DentalDepositTest|DentalWorkflowTest'`** and verify all pass.
- [ ] **Step 7: Commit** with `feat: collect dental treatment plan deposits`.

### Task 5: Reports, Labels, and Stadium Fail-Closed Contract

**Files:**
- Modify: `app/Services/Billing/BillingReportService.php`
- Modify: `app/Http/Resources/BillingInvoiceResource.php`
- Modify: `app/Http/Resources/BillingPaymentResource.php`
- Modify: `resources/js/pages/billing/Report.vue`
- Modify: `resources/js/pages/billing/Show.vue`
- Modify: `resources/js/pages/billing/Receipt.vue`
- Test: `tests/Feature/BusinessDepositReportTest.php`
- Test: `tests/Feature/SharedBillingTest.php`
- Test: `tests/Browser/reports-layout.spec.js`

**Interfaces:**
- Report totals sum signed payments, so refunds reduce collections without changing historical invoice totals.
- Invoice resources expose a stable source label and deposit/final classification.
- Unsupported business/source combinations return the existing business-module denial response and create no rows.

- [ ] **Step 1: Write failing report and Stadium tests** for net collections, payment-method breakdowns with refunds, invoice reconciliation, source labels, cross-currency isolation, and zero-row fail-closed behavior.
- [ ] **Step 2: Run focused tests** and verify failures are due to missing signed report and labels.
- [ ] **Step 3: Update report queries/resources and billing views** to show deposit/refund labels while preserving existing report structure.
- [ ] **Step 4: Add the explicit Stadium fail-closed test path** without introducing a route that accepts orphan payments.
- [ ] **Step 5: Run `php artisan test --filter='BusinessDepositReportTest|SharedBillingTest'` and the focused report browser test**.
- [ ] **Step 6: Commit** with `feat: report shared deposit activity`.

### Task 6: Deposit User Interfaces

**Files:**
- Create: `resources/js/components/billing/DepositPanel.vue`
- Create: `resources/js/components/billing/DepositCancellationFields.vue`
- Create: `resources/js/components/settings/AppointmentTypeDeposits.vue`
- Create: `resources/js/services/deposits.js`
- Modify: `resources/js/pages/salon/bookings/Index.vue`
- Modify: `resources/js/pages/appointments/Index.vue`
- Modify: `resources/js/pages/appointments/FormPage.vue`
- Modify: `resources/js/pages/dental/PatientDental.vue`
- Modify: `resources/js/components/dental/PlanEditor.vue`
- Modify: `resources/js/pages/settings/Index.vue`
- Modify: `resources/js/components/settings/SettingFields.vue`
- Test: `tests/Browser/business-deposits.spec.js`

**Interfaces:**
- `DepositPanel` receives a server summary, currency, enabled methods, authorization booleans, and a source descriptor; emits `collected` with the refreshed source.
- `DepositCancellationFields` binds disposition and idempotency key only when the summary reports collected funds.
- `deposits.js` maps Clinic, Dental, and Salon sources to their explicit endpoints; it has no Stadium endpoint.

- [ ] **Step 1: Write failing Playwright fixtures/tests** for configuration, full/partial collection, validation, receipt link, cancellation choice, busy/error states, permission-hidden controls, and desktop/mobile fit for all three workflows.
- [ ] **Step 2: Build once and run the focused browser test** to confirm failures arise from missing deposit UI.
- [ ] **Step 3: Implement the shared components/service** with generated idempotency keys retained across retry and reset after success.
- [ ] **Step 4: Integrate Salon, Clinic, Dental, and settings views** using only server-returned totals and permissions.
- [ ] **Step 5: Run `npm run build` and `npx playwright test tests/Browser/business-deposits.spec.js`** and verify they pass.
- [ ] **Step 6: Commit** with `feat: add business deposit interfaces`.

### Task 7: Full Regression and Delivery Check

**Files:**
- Modify only files required to correct regressions discovered by the commands below.

**Interfaces:**
- No new interfaces; this task validates the integrated contract from source configuration through receipt, final invoice, cancellation, and reporting.

- [ ] **Step 1: Run `php artisan migrate:fresh --seed` against the test database** and verify all migrations apply in order.
- [ ] **Step 2: Run `php artisan test`** and require the complete PHP suite to pass.
- [ ] **Step 3: Run frontend unit tests with the repository's required Node VM-modules flag** and require all to pass.
- [ ] **Step 4: Run focused and relevant Playwright suites** for deposits, appointments, Salon, Dental, shared billing, reports, and settings.
- [ ] **Step 5: Run `npm run build`**, `php artisan route:list`, `git diff --check`, and `git status --short`.
- [ ] **Step 6: Inspect final migration rollback order, public API payloads, audit metadata, and secret handling**; correct only deposit-related findings and repeat affected tests.
- [ ] **Step 7: Commit** with `test: verify shared business deposits` if verification required code/test corrections.
