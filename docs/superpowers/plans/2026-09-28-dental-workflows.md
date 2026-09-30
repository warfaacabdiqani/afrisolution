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
- [x] Write API tests for missing endpoints and isolation; run and observe failures.
- [x] Implement migration/models, access, validated catalog and dated chart history.
- [x] Run php artisan test --compact --filter=DentalWorkflowTest; expect pass.

## Task 2: Plans, visit completion, invoices
Files: app/Services/DentalPlanService.php, app/Services/Billing/DentalTreatmentBillingAdapter.php,
config/billing.php, DentalController.php, DentalWorkflowTest.php.
Interfaces: /patients/{patient}/plans, /plans/{plan}, /plans/{plan}/status,
/plans/{plan}/items/{item}/complete, /plans/{plan}/items/{item}/invoice.
- [x] Add failing tests for lifecycle, immutable prices, foreign appointments and retries.
- [x] Implement atomic draft edits, accept/cancel, completion and source billing adapter.
- [x] Run DentalWorkflowTest; expect pass including rejected foreign/invalid writes.

## Task 3: User interface and regression
Files: resources/js/pages/dental/{PatientDental,Procedures}.vue,
resources/js/components/dental/ToothChart.vue, resources/js/services/dental.js,
resources/js/router/index.js, patients/Show.vue, staff permissions, billing source label.
Interfaces consume task 1/2 APIs using shared context headers.
- [x] Add browser test for catalog, tooth finding, two-visit plan and invoicing.
- [x] Implement accessible tooth chart and catalog/plan forms with contextual actions.
- [x] Run browser test, full PHP suite, npm run build and git diff --check.
- [x] Independent final code review; address material findings and rerun affected checks.
- [x] Apply additive local migration and document permissions/usage and validation results.

## Progress
- Baseline: 173 tests passed (3,146 assertions); build passed during preceding audit.
- Numbering: user selected Universal (1–32 adult, A–T primary).

- Tasks 1/2 complete: missing endpoint tests observed failing; 19 focused tests passed (354 assertions).
- Full PHP suite: 186 passed, 3,283 assertions. Vite build passed with existing bundle warning.
- Review fixes: preserve independent surface findings; round quoted tax per treatment invoice.
- Database: local MySQL started; Dental and prerequisite receipt/report migrations applied.
- UI: full workflow, mobile read-only and surface summary pass; patient-switch race regression now passes.

- Browser suite: 4 passed, including the patient-switch regression. Root cause: prior component cleanup ran after the next profile started; onBeforeUnmount now invalidates the old load first.

- Independent review complete: no remaining consequential findings; verified surface summary and per-treatment tax corrections, isolation, permissions, immutable quotes, retries and patient guards.
