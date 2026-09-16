# Phase 3C.1 — Shared Billing Foundation

Implemented and verified on 2026-09-16. This phase stops at the shared foundation; no Clinic invoice-generation workflow was added.

## Architecture before and after

Previously, `BillingService::fromSalon()` directly loaded Salon appointments, copied prices, allocated invoice numbers, and wrote invoices. The shared ledger tables already existed, but the Clinic billing landing page displayed settings instead of the ledger.

Now the existing Salon endpoint calls `BillingService::createFromSource()`. The service resolves an allowlisted `InvoiceSource` adapter from `config/billing.php`. `SalonBillingAdapter` resolves the completed, authorized appointment and supplies its stored service snapshots. The financial core owns tenant and branch validation, customer resolution, calculation/reconciliation, numbering, monthly limits, persistence, idempotency, balance/status updates, and auditing. It contains no Patient, Doctor, Stylist, or SalonClient model logic.

`BillingLock` replaces the billing dependency on `BookingCore`, while preserving serialization on the same tenant row. This makes invoice creation and payment recording safe alongside booking changes. The existing source uniqueness constraint remains in place. Concurrent repeated invoice requests return one invoice and consume one number, even at the monthly limit.

Both Clinic and Beauty Salon now enter the same `BusinessBilling.vue` → `Ledger.vue` flow. `/api/v1/billing/*` remains the common API. There are no duplicate industry ledgers.

## Customer ownership

- Invoices have nullable `patient_id` and `salon_client_id` references.
- New invoices created by the shared service receive exactly one association, resolved server-side through the configured customer definition.
- Patient customers require a clinical business; Salon customers require the Salon capability. Salon billing itself never requires `clinical=true`.
- Customer queries are tenant scoped; Salon customer assignment must also match the source branch.
- The API exposes `customer: {type, id, name}` using `patient` / `salon_client` aliases, never model class names.
- `customer_name` remains the issued display-name snapshot. Renaming a client does not change an invoice or an idempotent response.
- Existing Salon invoices are backfilled through their tenant/branch-matched appointment source and customer relationship. There is no name matching. Unresolved legacy invoices remain intact with a null customer reference and their original name.

## Financial snapshots

New lines store source occurrence type/ID, description, quantity, unit price, discount amount, tax rate, tax amount, and final `line_total`. Salon line sources reference the booked appointment-service row, not the mutable service catalog.

`amount` deliberately retains its original meaning: the charge before invoice-level adjustments. It is not silently redefined as a tax-inclusive amount. New version-2 invoices have explicit final line totals. Legacy version-1 lines retain their original amounts and have null allocation fields where historical information was not recorded.

Calculations use integer cents and tax basis points. Invoice discounts are allocated using cumulative proportions; tax rounding is cumulative so line totals reconcile with the invoice. Source subtotal, discount, tax, and total are checked against the calculated values before issuance. Existing Salon pricing, discount, and tax policy remains the source of those inputs.

Invoices now include `issued_at`, nullable `due_at`, tax-rate snapshot, `snapshot_version`, and reserved void timestamp/reason. No cancellation workflow was introduced. Payments reject non-payable/voided invoices, preserve retry keys, prevent overpayment, and continue respecting enabled methods and the partial-payment setting.

## API and frontend

Dedicated invoice, item, and payment resources replace raw model serialization. Invoice details include tenant identity, branch, normalized customer/source, status, currency, amounts, central balance, items, payments, and issuance dates. Payment keys and internal actor fields are not serialized. Existing list pagination and invoice identifiers remain compatible.

Mutation requests prohibit frontend-supplied ownership and invoice totals. Prices and customer identity are obtained from server-resolved sources. Payment writes explicitly select validated fields.

The ledger displays Patient or Client using business-profile terminology, branch, total, paid, balance, status, invoice detail, and payment history. A shared API module and composable separate request/state behavior from rendering. On business or branch changes, state is cleared synchronously, in-flight requests are aborted, stale responses are ignored, and payment keys/form fields are reset. A business switch also clears the selected invoice route.

Customer filters, manual creation, receipts, printing, and reports remain future additions. The older patient-profile billing settings tab is not converted into a customer-history workflow in this foundation phase.

## Access and isolation

Access still requires billing capability AND the existing `billing` plan feature AND `billing.view`. Invoice creation additionally requires `billing.create`; payments require `billing.payments`. No new granular plan flags were enforced, so billing-enabled plans retain access.

Billing permissions now have their own shared configuration and staff permission controls. The existing Salon source endpoint retains appointment-view/source-visibility checks; no appointment-management or clinical permissions were added to cashiers. Cashiers can read invoices and record payments using billing permissions alone.

Tenant model scopes, authorized-branch queries, stale context headers, and database ownership protection remain intact. MySQL uses composite `(tenant_id, customer_id)` foreign keys. SQLite adds customer foreign keys in place plus tenant-consistency triggers, avoiding a destructive/rebuilding migration of populated ledger tables. Parent tenant changes are protected too. Shared billing no longer has a frontend clinical-capability exception; clinical report restrictions remain unchanged because reports are outside this phase.

## Migration and local data

Added `2026_09_16_100000_extend_shared_billing_foundation.php`. The Phase 3B migration is unchanged.

The migration adds ownership, metadata, line snapshots, and query indexes. It does not delete financial rows, regenerate numbers, reset sequences, or modify original financial amounts. It was applied successfully to the local MySQL `afri_clinic` database after writing a private financial-data/schema backup under `storage/app/private/billing-backups/`.

The local database contained zero invoice, item, and payment rows. Before/after comparisons confirmed unchanged original columns and invoice sequences. A separate populated SQLite migration test verifies preservation of legacy invoices, items, payments, numbers, sequences, and unresolved sources. No local database reset was performed.

## Files created

- `app/Services/Billing/InvoiceSource.php`
- `app/Services/Billing/SalonBillingAdapter.php`
- `app/Services/Billing/BillingCustomerResolver.php`
- `app/Services/Billing/BillingLock.php`
- `app/Services/Billing/BillingMoney.php`
- `app/Http/Requests/BillingPaymentRequest.php`
- `app/Http/Requests/BillingSourceRequest.php`
- `app/Http/Resources/BillingInvoiceResource.php`
- `app/Http/Resources/BillingInvoiceItemResource.php`
- `app/Http/Resources/BillingPaymentResource.php`
- `config/billing.php`
- `database/migrations/2026_09_16_100000_extend_shared_billing_foundation.php`
- `resources/js/services/billing.js`
- `resources/js/composables/useBillingLedger.js`
- `tests/Feature/SharedBillingTest.php`
- `tests/Feature/SharedBillingConcurrencyTest.php`
- `tests/Support/shared-billing-worker.php`
- `tests/Unit/BillingMoneyTest.php`
- `tests/Frontend/billing-context.test.mjs`
- `tests/Browser/shared-billing.spec.js`
- `playwright.billing.config.js`
- `docs/phase-3c1-shared-billing.md`

## Files modified

- `app/Services/BillingService.php`
- `app/Http/Controllers/BillingController.php`
- `app/Http/Controllers/ClinicStaffController.php`
- `app/Models/BillingInvoice.php`
- `config/clinic.php`
- `resources/js/pages/billing/BusinessBilling.vue`
- `resources/js/pages/billing/Ledger.vue`
- `resources/js/pages/staff/Index.vue`
- `resources/js/router/index.js`

## Verification

PHP 8.4 was used from `C:/php84/php.exe`; the default shell PHP is an older installation.

- `php artisan test --filter=SalonBookingTest`: 7 passed, 218 assertions.
- Final focused PHP run: `php artisan test --filter='BillingMoneyTest|SharedBilling|SalonBookingTest|BusinessModuleAccessTest|ClinicSettingsTest|SalonCoreTest'`: 43 passed, 928 assertions.
- `node --experimental-vm-modules --test tests/Frontend/billing-context.test.mjs`: 3 passed. Tests exercise real Vue lifecycle/reactivity with intentionally late responses and verify context clearing, ignored responses, headers, and payment-key lifetime.
- `npx playwright test --config=playwright.billing.config.js`: 2 passed. Both Clinic and Salon render the built shared ledger, use the appropriate customer label, open details, and submit payment. These UI contract tests intercept API responses; backend behavior is covered by the PHP tests.
- `npm run build`: passed.
- Billing and Salon invoice route registration: checked; no Clinic invoice-generation endpoint was added.
- Migration status: new additive migration applied as batch 21 locally.
- Original Phase 3B migration: unchanged.

New PHP tests cover ownership, historical snapshots, line reconciliation, partial/full payments, idempotency, overpayment, capability/plan/permission separation, cross-tenant IDs in both Clinic/Salon directions, branch restrictions, database customer constraints, monthly limits, disabled methods/partial payments, populated migration preservation, tiny/large monetary rounding, and concurrent invoice generation.

## Remaining scope

No known failing Phase 3C.1 checks remain. Legacy unresolved customer links and unrecorded line allocations are intentionally preserved rather than guessed. Clinic appointment invoice generation, medicine billing, receipt generation, inventory, products/POS, gateways, and financial reports were not implemented. Phase 3C.2 has not started.
