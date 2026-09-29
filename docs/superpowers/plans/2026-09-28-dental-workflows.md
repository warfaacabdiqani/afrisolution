# Dental Workflows Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans. Track steps below.

**Goal:** Deliver patient charting/history, dental catalog/pricing and multi-visit plans.
**Architecture:** Dental domain tables and services reuse ClinicAccessService and billing.
**Tech Stack:** Laravel 12, SQLite-compatible migrations, Vue 3, PHPUnit, Playwright.
**Spec:** docs/superpowers/specs/2026-09-28-dental-workflows-design.md

## Global Constraints
Dental-only; EMR entitlement; named permissions; Universal adult/primary teeth; tenant and branch
isolation; immutable history and quote snapshots; no hard deletion of clinical records.

## Review Focus
- Foreign tenant/unauthorized branch references must never expose or mutate clinical data.
- Catalog changes must not alter accepted plans or completed treatment charges.
- Duplicate completion/invoicing must not duplicate treatment or payment obligations.
- Archived patients and terminal plans must reject clinical mutations.
- Context changes must clear prior patient data and reject stale responses.

## Task 1: Dental data, access, catalog and chart
Files: config/dental.php, config/clinic.php, config/business_types.php;
database/migrations/*_create_dental_workflows.php; app/Models/Dental*.php;
app/Services/DentalAccessService.php; app/Services/DentalChartService.php;
app/Http/Controllers/DentalController.php; tests/Feature/DentalWorkflowTest.php.
Interfaces: /api/v1/dental/options, /procedures, /patients/{patient}/chart,
/patients/{patient}/findings, /patients/{patient}/findings/{finding}/void.
- [ ] Write API tests for missing endpoints and isolation; run and observe failures.
- [ ] Implement migration/models, access, validated catalog and dated chart history.
- [ ] Run php artisan test --compact --filter=DentalWorkflowTest; expect pass.

## Task 2: Plans, visit completion, invoices
Files: app/Services/DentalPlanService.php, app/Services/Billing/DentalTreatmentBillingAdapter.php,
config/billing.php, DentalController.php, DentalWorkflowTest.php.
Interfaces: /patients/{patient}/plans, /plans/{plan}, /plans/{plan}/status,
/plans/{plan}/items/{item}/complete, /plans/{plan}/items/{item}/invoice.
- [ ] Add failing tests for lifecycle, immutable prices, foreign appointments and retries.
- [ ] Implement atomic draft edits, accept/cancel, completion and source billing adapter.
- [ ] Run DentalWorkflowTest; expect pass including rejected foreign/invalid writes.

## Task 3: User interface and regression
Files: resources/js/pages/dental/{PatientDental,Procedures}.vue,
resources/js/components/dental/ToothChart.vue, resources/js/services/dental.js,
resources/js/router/index.js, patients/Show.vue, staff permissions, billing source label.
Interfaces consume task 1/2 APIs using shared context headers.
- [ ] Add browser test for catalog, tooth finding, two-visit plan and invoicing.
- [ ] Implement accessible tooth chart and catalog/plan forms with contextual actions.
- [ ] Run browser test, full PHP suite, npm run build and git diff --check.
- [ ] Independent final code review; address material findings and rerun affected checks.
- [ ] Apply additive local migration and document permissions/usage and validation results.

## Progress
- Baseline: 173 tests passed (3,146 assertions); build passed during preceding audit.
- Numbering: user selected Universal (1–32 adult, A–T primary).
