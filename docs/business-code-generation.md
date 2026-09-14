# Automatic business codes

New businesses receive a server-generated identifier in the existing `tenants.slug` field: `{PREFIX}-{TYPE}-{SEQUENCE}`. No business-specific code columns were added.

## Settings and format

- **Business Code Prefix** uses `general.code_prefix`, defaults to `AFRI`, and is normalized to uppercase on save. Validation allows 1–20 letters/numbers.
- **Starting Sequence** uses the new `general.business_code_start`, defaults to `200001`, and accepts positive integers up to `999999999999`.
- The old **Code Contains** value appended literal suffix text; it did not represent a sequence or code length. Its stored value is retained and displayed as a readonly **Legacy Code Suffix**. It is not used by the new generator.
- Starting Sequence initializes the first generated code only. After initialization, allocation always advances the global counter. Changing the starting setting never resets or advances an active counter. A changed setting below the current counter is rejected with “Starting sequence cannot be lower than the current generated sequence.” Saving an unchanged initialization setting remains allowed.
- System Settings displays backend-generated next-code examples for active business types and draft settings. Examples do not reserve codes.

The central mapping lives in `config/business_types.php`:

| Business type | Code |
| --- | --- |
| clinic | CLN |
| dental | DEN |
| beauty-salon | SAL |
| stadium | STD |
| Future unconfigured type | BUS |

Type abbreviations are validated as 2–5 uppercase letters. Vue contains no abbreviation or identifier-format mapping.

## Allocation, persistence, and audit

`BusinessCodeGenerator` reads code settings directly from the database. `platform_sequences` has an ID, unique key, current value, and timestamps; its `business_code` row starts at zero, meaning uninitialized.

Tenant creation locks this row before reading provisioning data. The generator selects the next available identifier, updates the counter, and creates the tenant, owner, membership, location, subscription, and audits in the same transaction. Transactions retry concurrency failures up to five times. Row-locking databases use `lockForUpdate()`; SQLite first takes a write lock with a no-op update, avoiding read-to-write lock upgrades under contention.

General-settings changes take the same sequence lock. Existing matching identifiers are skipped. Submitted `slug`, `business_code`, and formatting overrides are never authoritative. Failed provisioning rolls back the counter and generated-code audit along with the tenant.

Audit events:

- `tenant.business_code.generated`: final code and business type ID.
- `platform.business_code_settings.updated`: only the before/after code prefix and starting sequence.

## API and frontend

- `GET /api/v1/platform/businesses/next-code?business_type_id=3` returns `{ "code": "AFRI-SAL-200001" }`. Requires authenticated platform access and `tenants.manage`; the type must be active.
- `GET /api/v1/platform/businesses/code-examples` accepts optional `code_prefix` and `business_code_start` draft settings. Requires authenticated platform access and `settings.view`.
- The existing create endpoint remains `POST /api/v1/platform/tenants`.
- `/app/admin/clinics/create` and its new alias `/app/admin/businesses/create` display a readonly preview with dynamic Clinic/Dental Clinic/Salon/Stadium Code labels and the helper “Generated automatically from platform settings.”
- Type changes refresh the preview. Request-version checks discard stale responses and results arriving after unmount. The preview is explicitly nonbinding; the final code is allocated on creation.

## Migration and compatibility

`2026_09_15_000001_create_platform_sequences_table.php` adds only the sequence table and initial row. It was applied locally; a before/after comparison confirmed all three existing tenant codes were unchanged. The existing `tenants_slug_unique` database index was verified, so no duplicate constraint or tenant rewrite was introduced. Other environments must run the additive migration before using the updated creation/settings flow.

## Files

| Area | Files added or modified |
| --- | --- |
| Generator and creation | `app/Services/BusinessCodeGenerator.php`, `app/Services/PlatformService.php` |
| Configuration and migration | `config/business_types.php`, `database/migrations/2026_09_15_000001_create_platform_sequences_table.php` |
| API and validation | `app/Http/Controllers/BusinessCodeController.php`, `app/Http/Controllers/SystemSettingsController.php`, `app/Http/Requests/SystemSettingsRequest.php`, `app/Http/Requests/TenantRequest.php`, `routes/api.php` |
| Frontend | `resources/js/pages/admin/clinics/ClinicCreate.vue`, `resources/js/pages/admin/settings/SettingsIndex.vue`, `resources/js/router/index.js` |
| New tests | `tests/Feature/BusinessCodeTest.php`, `tests/Feature/BusinessCodeConcurrencyTest.php`, `tests/Support/business-code-worker.php`, `tests/Browser/business-code.spec.js`, `tests/Browser/business-code-fixtures.php` |
| Existing regression fixtures/assertions | `tests/Feature/ClinicSettingsTest.php`, `tests/Feature/PatientManagementTest.php`, `tests/Feature/PrescriptionManagementTest.php`, `tests/Feature/SaasFoundationTest.php` |
| Report | `docs/business-code-generation.md` |

Existing patient/prescription tests explicitly retain legacy tenant identifiers in their fixtures, so their historical patient-number behavior remains covered. Platform assertions now account for the additional generated-code audit.

## Verification

Backend tests cover all four mappings, future fallback, non-reserving previews, permissions, inactive/invalid types, forged and stale submitted codes, sequence increments, settings validation/normalization, active-counter protection, literal numeric prefixes, rollback, legacy preservation, collision skipping, and the unique constraint. A separate-process SQLite test starts two workers behind a shared barrier and forces lock contention; both commit distinct consecutive codes.

- `php artisan test --compact`: **104 passed, 1,899 assertions**.
- `npx playwright test tests/Browser/business-code.spec.js`: **1 passed**. Verified settings save/reload, all four previews, readonly fields, delayed stale-response rejection, salon creation without a submitted code, the next clinic sequence, both create URLs, and no page errors or Vue warnings.
- `npm run build`: **passed**, 255 modules transformed. The existing Vite large-chunk warning remains; the main JavaScript bundle is approximately 509 kB before gzip.
- `git diff --check`: passed.
- Local migration: applied; all three existing tenant identifiers unchanged; `tenants_slug_unique` verified.

Concurrency execution was verified against SQLite using separate PHP processes. The row-locking path for other database engines was implemented but not integration-tested against a running MySQL/PostgreSQL server in this workspace.
