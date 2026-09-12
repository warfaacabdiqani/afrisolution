# Clinic Settings

Clinic Settings is available at `/app/settings`, which redirects to `/app/settings/general`. It uses the existing clinic shell and is separate from Platform System Settings at `/app/admin/settings`.

## Settings architecture

`ClinicSettingsService` owns tenant settings access. The new `tenant_settings` table stores one JSON object per tenant and section, with a unique `(tenant_id, section)` key. This follows the existing platform settings service's section-based approach without mixing tenant preferences into the global `system_settings` table.

`config/clinic_settings.php` centralizes section defaults, feature requirements, field descriptions and Laravel validation. The frontend uses those descriptions to render consistent individual forms. Cached values use tenant-specific keys and are invalidated on changes and transaction commit. The existing tenant row remains authoritative for the clinic name, code, timezone and operational status.

No integration credentials are accepted. Unknown settings are rejected rather than stored as arbitrary secrets. The frontend never receives branding filesystem paths or platform credentials. If credential-bearing integrations are introduced later, they require encrypted, masked fields rather than additions to this plain preference schema.

## Sections and behavior

| Section | Behavior |
| --- | --- |
| General | Clinic name, contacts, website, address, timezone, currency and supported language. Code and operational status remain platform-managed and read-only. Timezone changes are rejected when appointments already exist, protecting their stored local times. |
| Branding | Clinic, small, receipt, prescription and invoice logos, plus stamp. Local previews precede upload. Accepts PNG/JPEG/WEBP up to 2 MB and 4000×4000 pixels. SVG is rejected. |
| Branches | Authorized branch listing, add/edit dialog, contact details, timezone metadata, status and stable main-branch identification. The main branch and the user's final active accessible branch cannot be disabled. Future bookings must be resolved before deactivation. |
| Patient Settings | Prefix, number length, duplicate warnings and required patient fields. Existing first/last name and gender requirements cannot be weakened. |
| Appointments | Booking duration, slot interval, default working hours, walk-in/same-day policy, reminder timing and cancellation policy. Existing schedule/leave/overlap validation remains authoritative. Double booking and overbooking remain disabled. |
| Clinical | Consultation preferences for the future encounter module, notes template and prescription validity. Completed-record editing remains disabled. |
| Pharmacy | Feature-gated stock preferences, FEFO, warning thresholds, batch/expiry requirements and accessible default branch. Negative stock stays disabled. Stock movements are not performed by Settings. |
| Billing | Feature-gated future invoice/receipt prefixes, number length, tax, fee, currency, discount policy, payment methods, partial-payment and refund preferences. No gateway is simulated. |
| Notifications | Feature-gated channel/event preferences. Channels absent from the plan are disabled. Provider status explicitly says Not configured; saving preferences sends no messages. |
| Documents | Headers, footers and display controls. Saved settings feed prescription printing and sample prescription/invoice/receipt previews. |
| Security | Enforced timeout and minimum staff password length, plus clinic staff-access policy. Clinic policy cannot weaken platform defaults. Unsupported 2FA and first-login password-change controls are disabled. |
| Subscription | Real plan, trial dates, subscription status, enabled features and usage for branches, active members, clinicians, retained patients and private storage. Upgrade guidance uses configured platform support contact; there is no subscription mutation endpoint. |

Forms save only the current section. Save is disabled until values change; Discard restores the saved values. Navigation prompts protect unsaved edits, including tab changes, branch switching and sign-out. Mobile uses a category dropdown and single-column forms. Dialogs use native keyboard focus management.

## Operational integrations

- Patient registration uses the configured prefix and length under the existing tenant sequence lock. It checks for collisions after prefix changes, preserves every existing number, honors duplicate-warning preferences and validates configured required fields server-side.
- Appointment creation uses clinic duration defaults; available slots use the configured interval. Clinician schedule editors initialize new working hours from the clinic defaults. Walk-in and same-day restrictions are enforced in the booking service. Reminder timing and cancellation-policy text are preferences for future notification/self-service workflows; they do not simulate reminders or patient self-service cancellation.
- Branches marked inactive disappear from operational branch selection while remaining manageable by authorized settings users. Branch timezone is metadata; the existing scheduling system continues to use the clinic timezone.
- New prescriptions snapshot the validity default into `expires_on`. Changing the default does not rewrite existing prescriptions. The date appears in prescription details and print output. This setting does not introduce an automatic expiry-status scheduler.
- Prescription printing uses the actual clinic name/contact information, private logo, header/footer, license visibility and signature visibility. Internal notes remain excluded. The previously added print padding is preserved.
- Private branding assets and patient documents both count toward storage quota. File replacement removes the previous asset after the database commit.
- Staff access restrictions apply to the selected clinic, not a user's other memberships. Settings managers retain recovery access based on permissions, not role names. Backend requests enforce session expiry; the clinic layout also logs out after user inactivity and renews active editing sessions through bounded activity heartbeats. Staff provisioning applies the stricter clinic/platform password requirements.

Consultation, full Pharmacy inventory, Billing documents and notification delivery are not implemented by this module. Their preferences are explicitly marked for the future workflows. No existing invoice or receipt identifiers are rewritten. English remains the supported application language.

## API endpoints

All endpoints use the existing authenticated `/api/v1/clinic` tenant middleware and session context.

| Method | Path |
| --- | --- |
| GET | `/settings` |
| PUT | `/settings/{section}` for General, Patients, Appointments, Clinical, Pharmacy, Billing, Notifications, Documents and Security |
| GET | `/settings/branches` |
| POST | `/settings/branches` |
| PUT | `/settings/branches/{branch}` |
| POST | `/settings/branding` (multipart `asset`, `file`) |
| GET | `/settings/branding/{asset}` (authenticated private image) |
| GET | `/settings/preview/{prescription|invoice|receipt}` |
| GET | `/settings/subscription` |

Branding uses multipart POST to follow the project's upload conventions. Asset lookup resolves the current tenant; neither a tenant query parameter nor another clinic's path can select ownership. Responses use private/no-store headers. Preview values are escaped by Blade.

## Permissions and limits

`clinic_settings.view` is required to open settings. Updates require either `clinic_settings.update` or the section-specific `clinic_settings.{section}.update` permission. Branch operations use `clinic_settings.branches.manage`. These permissions are included in the existing member permission registry. Existing wildcard and explicit membership permissions remain authoritative.

Feature gates are enforced by the API and reflected in the tab list. Branch creation additionally requires multi-branch support and respects the subscription branch limit under the shared tenant lock. Branch updates validate tenant ownership and membership branch access. Pharmacy's default branch must be accessible to the user. Stale clinic/branch headers retain the existing 409 protection.

Audit events are `clinic.settings.general.updated`, `.branding.updated`, `.patient.updated`, `.appointments.updated`, `.clinical.updated`, `.pharmacy.updated`, `.billing.updated`, `.notifications.updated`, `.documents.updated`, `.security.updated`, plus `branch.created` and `branch.updated`. The existing audit service records the actor, tenant, timestamp, branch reference where applicable and changed field names. It does not copy contact details, templates or secrets into generic audit metadata.

## Files created

- `config/clinic_settings.php`
- `database/migrations/2026_09_12_010000_create_clinic_settings.php`
- `database/migrations/2026_09_12_020000_add_prescription_validity.php`
- `app/Services/ClinicSettingsService.php`
- `app/Services/ClinicDocumentService.php`
- `app/Http/Controllers/ClinicSettingsController.php`
- `app/Http/Controllers/ClinicSettingsBranchController.php`
- `app/Http/Controllers/ClinicBrandingController.php`
- `app/Http/Requests/ClinicSettingsRequest.php`
- `app/Http/Requests/ClinicBranchRequest.php`
- `app/Http/Requests/ClinicBrandingRequest.php`
- `resources/js/pages/settings/Index.vue`
- `resources/js/components/settings/SettingFields.vue`
- `resources/js/components/settings/SettingsDialog.vue`
- `resources/js/components/settings/BrandingSettings.vue`
- `resources/js/components/settings/BranchSettings.vue`
- `resources/js/components/settings/SubscriptionSettings.vue`
- `resources/js/components/settings/UsageProgress.vue`
- `resources/js/components/settings/DocumentPreviews.vue`
- `resources/js/services/clinicSettings.js`
- `resources/js/stores/clinicSettings.js`
- `resources/css/clinic-settings.css`
- `resources/views/clinic/document-header.blade.php`
- `resources/views/clinic/document-preview.blade.php`
- `tests/Feature/ClinicSettingsTest.php`
- `tests/Browser/z_settings.spec.js`

## Files modified

- `routes/api.php`, `resources/js/router/index.js`: section routes and protected navigation.
- `config/clinic.php`, `app/Http/Requests/MemberRequest.php`: settings permissions and staff password policy.
- `app/Services/ClinicAccessService.php`, `resources/js/stores/clinicContext.js`, `resources/js/layouts/ClinicLayout.vue`: active branches, tenant access/timeout policy and unsaved-change protection. Shell visuals are preserved.
- `app/Services/TenantProvisioningService.php`: reuse existing branch provisioning and audit infrastructure for additional branch attributes.
- `app/Services/PatientService.php`, `app/Http/Requests/PatientRequest.php`, `app/Http/Controllers/PatientDocumentController.php`: numbering, required fields, duplicate preferences and combined storage quota.
- `app/Services/AppointmentService.php`, `app/Services/AppointmentAvailabilityService.php`, `app/Http/Controllers/AppointmentController.php`, `resources/js/components/appointments/AppointmentForm.vue`: booking policies and defaults.
- `app/Http/Controllers/DoctorScheduleController.php`, `resources/js/pages/doctors/tabs/Schedule.vue`: default working times for new schedule entries.
- `app/Services/PrescriptionService.php`, `app/Http/Resources/PrescriptionResource.php`, `resources/js/pages/prescriptions/Show.vue`: prospective validity date.
- `app/Http/Controllers/PrescriptionPrintController.php`, `resources/views/prescriptions/print.blade.php`: settings-backed patient-facing document output.
- `tests/Feature/AppointmentManagementTest.php`: time-travel tests establish a fresh session after advancing beyond the newly enforced inactivity timeout.

## Verification

```powershell
C:/php84/php.exe artisan test --compact
npm run build
npx playwright test tests/Browser/z_settings.spec.js tests/Browser/z_prescriptions.spec.js
```

The dedicated settings tests cover persistence, immutable identifiers, numbering and required fields, booking preferences, branch limits, tenant/cache/asset isolation, granular permissions, feature restrictions, branding validation, document previews, stronger security policy, inactivity expiry, staff-access recovery, real usage and prescription validity snapshots. Browser checks cover the user workflows, unsaved edits, uploads, mobile layout and console/page errors, with separate prescription regression coverage.

Both migrations have been applied to the configured local MySQL database. They add the previously missing `branches.status` column, the tenant settings store and prospective prescription validity metadata.

Final results: **71 backend tests passed (1,199 assertions)**, both settings and prescription browser workflows passed, and the production build succeeded. The final scheduling check passed with 8 tests / 260 assertions. Settings browser checks reported no application console or page errors after authentication. Desktop and mobile screenshots are saved in `storage/framework/testing/settings-desktop.png` and `settings-mobile.png`. `git diff --check` passed.
