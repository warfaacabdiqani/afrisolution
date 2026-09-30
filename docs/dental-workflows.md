# Dental workflows

Dental businesses now have a procedure catalog and a **Dental Chart & Plans** patient tab.
The existing patient, appointment, prescription, invoice, receipt, and payment workflows
remain shared with Clinic. Other business types cannot access Dental APIs.

## Using the module

1. Open **Dental Procedures** in the sidebar. Add your own procedure codes, names and
   default prices. Use Active to archive a procedure without deleting existing references.
2. Open a patient and choose **Dental Chart & Plans**. Select Adult or Primary teeth,
   then a tooth, finding and optional surfaces/notes. Each finding records its author/date.
   Correct mistakes with **Mark entered in error** and a reason; the original is retained.
3. Create a treatment plan, choose procedures and assign visit numbers. Items support a
   specific tooth or a whole-mouth procedure, quantity, agreed price and notes. Save a
   draft, edit it as needed, then **Accept Plan** when the treatment and prices are agreed.
4. Complete individual treatments as care is delivered. Optionally select a matching
   in-consultation or completed appointment. Completion adds the item to tooth history.
   The plan completes when all items complete. Cancellation retains completed care and
   cancels remaining work.
5. Use **Create Invoice** on a completed treatment. The shared billing page records
   payments and prints invoices/receipts. Repeating issuance returns the same invoice.

## Numbering and history

The user-selected Universal system uses adult teeth 1–32 and primary teeth A–T, with
patient right shown on the left of the screen. The arrangement follows the
[ADA Universal Tooth Designation System](https://www.ada.org/-/media/project/ada-organization/ada/ada-org/files/publications/cdt/universal_tooth_designation_system_valueset_2.pdf).
Supernumerary teeth are not included in these standard adult/primary chart views.
Uncharted is distinct from Sound. The chart summarizes the latest non-voided finding for
each recorded surface and the whole tooth independently; a Sound finding on one surface
does not hide a finding on another. The history retains all dated observations.

## Access

- Exact business type: `dental`; business capability: `dental`; existing plan feature: `emr`.
- Patient chart and plans also require `patient_management` and `patients.view`.
- `dental.view`: read catalog/chart/plans.
- `dental.chart`: record/correct findings.
- `dental.procedures.manage`: create, edit and archive catalog entries.
- `dental.plans.manage`: create/edit drafts, accept/cancel plans.
- `dental.treatments.complete`: complete treatments and select eligible appointments.
- Invoice creation additionally requires the Billing plan feature and `billing.view` /
  `billing.create`; recording payments retains the existing billing permissions.

Owners/admins retain wildcard access. The doctor role receives Dental clinical permissions;
catalog management can be granted separately in **Users / Staff → Dental Permissions**.
Explicit custom permission lists are preserved. Existing Dental plans with EMR enabled gain
the module automatically; no subscription or price changes are made.

## Data and accounting

Four new tables: `dental_procedures`, `dental_findings`, `dental_plans`, `dental_plan_items`.
Composite foreign keys enforce tenant ownership of patients, branches, procedures, plans,
and linked appointments. Patient records remain tenant-wide; Dental findings/plans/history
are restricted to the user's authorized branches. Archived patients are clinically read-only.

Catalog prices and names become snapshots on draft items. Currency and tax are captured
when the plan is created. Accepted plans cannot be edited. Draft saves and status changes
check a version to reject stale changes. Mutations serialize using the shared tenant lock.
Clinical notes are not copied into general audit metadata.

Each completed item is one `dental_treatment` invoice source. Tax is rounded per treatment,
so the accepted plan total matches the sum of its treatment invoices. Invoices reuse
existing quotas, source uniqueness, payment idempotency, receipts, and billing reports.
An accepted estimate itself is not an invoice. Cancelled plans retain their original
estimate and completed items remain billable.

## Installation and verification

Apply the additive migration `2026_09_28_100000_create_dental_workflows.php` and build assets
with `npm run build`. The shared billing receipt/report migrations are prerequisites for
the existing receipt and billing-report screens. The local database has been updated.

Tests:

```text
php artisan test --compact
npm run build
npx playwright test --config=playwright.dental.config.js
```

The Dental feature tests use real migrations/APIs in an isolated database. Browser tests
exercise the built Vue screens with intercepted API responses, following the existing
billing browser-test approach; they do not send clinical data to external services.

Verified on 2026-09-30: 186 PHP tests passed (3,283 assertions), all four Dental browser
tests passed, and the Vite production build passed. The build retains the existing
main-bundle size warning. Desktop/mobile screenshots were visually inspected. Independent
code review found no remaining consequential issues after the surface-summary and
per-treatment tax-rounding fixes.
