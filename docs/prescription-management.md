# Prescriptions management

The existing clinic shell now hosts the prescription list, creation, editing, details and print pages. Statistics, search, filters and pagination use the backend. No sample clinical data is installed into the application database.

## Files created

- `database/migrations/2026_09_12_000000_create_prescription_management.php`
- Models: `app/Models/Prescription.php`, `PrescriptionItem.php`, `Medication.php`
- Services: `app/Services/PrescriptionService.php`, `PrescriptionDispensingService.php`
- Controllers: `app/Http/Controllers/PrescriptionController.php`, `PrescriptionQueryController.php`, `PrescriptionPrintController.php`
- Validation and serialization: `app/Http/Requests/PrescriptionRequest.php`, `app/Http/Resources/PrescriptionResource.php`
- Central configuration: `config/prescriptions.php`, `app/Support/PrescriptionStatus.php`, `resources/js/config/prescriptions.js`
- Vue pages: `resources/js/pages/prescriptions/Index.vue`, `FormPage.vue`, `Show.vue`, `Print.vue`
- Vue components: `resources/js/components/prescriptions/PrescriptionTable.vue`, `PrescriptionStatusBadge.vue`, `PrescriptionActions.vue`, `PatientSelector.vue`, `MedicationRow.vue`
- Shared patient/doctor history: `resources/js/pages/patients/tabs/Prescriptions.vue`
- State and API: `resources/js/stores/prescriptions.js`, `resources/js/services/prescriptions.js`
- Styles and print template: `resources/css/prescriptions.css`, `resources/views/prescriptions/print.blade.php`
- Tests: `tests/Feature/PrescriptionManagementTest.php`, `tests/Browser/z_prescriptions.spec.js`

## Files modified

- `routes/api.php`, `resources/js/router/index.js`: protected module routes.
- `config/clinic.php`, `app/Http/Requests/MemberRequest.php`: prescription permissions and role defaults.
- `app/Models/Patient.php`, `Doctor.php`, `Appointment.php`: prescription relationships.
- `resources/js/pages/patients/Show.vue`, `resources/js/pages/doctors/Show.vue`: permission-aware history tabs.
- `resources/js/components/appointments/AppointmentDrawer.vue`: Create Prescription with patient, prescriber, branch and appointment preselected.

## Database and medication architecture

The migration adds `prescriptions`, `prescription_items`, `medications`, and a per-tenant prescription sequence. Composite foreign keys enforce tenant ownership for the branch, patient, clinician, appointment, medication and item relationships. Lookup, date, status and number indexes support listing and filtering.

Numbers use `RX-000001` within each tenant. The tenant sequence is incremented under a database row lock in the same transaction as the prescription and its items. Medication changes and dispensing also run transactionally. Prescription records have no delete endpoint.

The tenant medication catalog starts empty. Authorized catalog managers can add entries from the medication builder. Search supports medication and generic names. A clinician may enter a medication name directly when it is absent from the catalog; the order preserves the prescribed name, strength, form, dose, route, coded frequency, custom frequency, duration, quantity and instructions. No medication recommendations or automatic dosing are generated.

## API endpoints

All paths below are prefixed with `/api/v1/clinic` and require authenticated tenant context.

| Method | Path | Purpose |
| --- | --- | --- |
| GET / POST | `/prescriptions` | Paginated list / create |
| GET | `/prescriptions/stats` | Real branch-scoped totals |
| GET | `/prescriptions/options` | Prescribers, status labels, routes, frequencies |
| GET | `/prescriptions/patients` | Accessible patient search |
| GET | `/prescriptions/appointments` | Matching appointment lookup |
| GET / PUT | `/prescriptions/{id}` | Details / guarded edit |
| POST | `/prescriptions/{id}/cancel` | Cancel with required reason |
| POST | `/prescriptions/{id}/send-to-pharmacy` | Queue an active prescription |
| POST | `/prescriptions/{id}/dispense` | Record absolute item dispensing totals |
| GET | `/prescriptions/{id}/activity` | Paginated audit history |
| GET | `/prescriptions/{id}/print` | Patient-facing HTML with no internal notes |
| GET | `/medications/search` | Tenant catalog search |
| POST | `/medications` | Permission-checked catalog entry creation |

List parameters: `search`, `status`, `doctor_id`, `branch_id`, `patient_id`, `from`, `to`, `medication`, `page`, `per_page` (maximum 100). Search includes prescription number, patient name/number/phone, medication and prescriber.

## Workflow

- Save Draft creates a draft; Save Prescription creates an active prescription or activates a draft. Required medication directions are validated for both.
- Draft, active and pending prescriptions are editable until any dispensing occurs. Editing a queued prescription preserves its pending state.
- Sending an active prescription to Pharmacy makes it pending.
- Absolute dispensing totals advance pending → partially dispensed → dispensed. Totals cannot decrease or exceed the prescribed quantity; repeating a total does not dispense additional units. Quantities must be specified before dispensing.
- Cancellation requires a reason and retains the record, actor, timestamp and any dispensing history.
- Partially dispensed, dispensed, completed, cancelled and expired prescriptions cannot be edited. Completed and expired are recognized terminal states for future lifecycle integrations; this module does not automatically infer expiry or completion dates.
- Duplicate opens a new, unsaved form. It clears the old appointment and internal notes and uses today's date. A new number is assigned only after saving.

## Integration boundaries

Patient and doctor profiles expose paginated prescription history under prescription permissions. Appointments can launch a prefilled prescription; the backend checks that patient, prescriber and branch match the selected appointment.

There is no consultation model or working consultation module in this repository. `consultation_id` is reserved and remains null; non-null client submissions are rejected until an actual consultation can be validated. No fictional consultation record or duplicate module was created.

Pharmacy remains its existing placeholder. The information banner and send action are visible only when its feature and permission are available. The queue, item quantities, dispensing statuses and `PrescriptionDispensingService` provide its integration contract. No inventory, stock movements, refills or billing module is implemented here.

## Authorization and privacy

Permissions: `prescriptions.view`, `.create`, `.update`, `.cancel`, `.print`, `.dispense`, `.view_all_doctors`, `.medications.manage`. Existing explicit membership overrides remain authoritative. Doctor defaults permit their own prescribing workflow; pharmacist defaults permit cross-prescriber viewing and dispensing. Owners and admins retain the existing wildcard permission.

The subscription feature, active subscription and clinic membership are enforced by the backend and router. The sidebar already filters inaccessible modules. Tenant IDs come from the session, never client ownership fields. Every prescription query is restricted to accessible branches; users without `view_all_doctors` see only prescriptions from their linked clinician. Selected patients must be active and registered in an accessible branch. Selected clinicians must be active and assigned to the prescription branch. Stale branch context returns 409.

Print HTML contains clinic/branch identity, prescription number/date/status, patient identity, prescriber specialty/license, medication directions, public notes and a signature area. Internal notes, database IDs and audit events are excluded. Clinic contact information is included if it becomes configured; the current tenant schema has no contact/logo configuration.

Audit events use the existing audit infrastructure: `prescription.created`, `.updated`, `.cancelled`, `.printed`, `.sent_to_pharmacy`, `.dispensed`. Metadata contains record references rather than diagnosis, medication directions or clinical notes.

## Verification

Run with the supported PHP executable (this workstation's default PHP 8.2 is older than the application's PHP requirement):

```powershell
C:/php84/php.exe artisan test --compact
npm run build
npx playwright test tests/Browser/z_prescriptions.spec.js
```

Backend coverage includes multi-item creation, drafts, validation and nested-field injection, updates, cancellation, unique numbering, search, filters, date boundaries, pagination, statistics, catalog ownership, appointment linkage, tenant/branch/prescriber isolation, permissions, feature restrictions, stale branch context, audit privacy, print privacy and dispensing restrictions.

Browser coverage includes creation, draft activation, printing, duplicate-and-save, cancellation, patient and doctor history, Pharmacy queueing, search/reset, real statistics and mobile cards. It checks for page errors and mobile horizontal overflow. Desktop and mobile screenshots are saved under `storage/framework/testing/` using the isolated browser fixture database.

The migration has been applied to the configured local application database. The clinic shell was not redesigned.

Final results: the full backend suite passed with **59 tests / 971 assertions**; the prescription subset passed with **10 tests / 189 assertions**. The production Vite build passed. The prescription browser workflow passed, including the mobile filter drawer, patient/doctor history and Pharmacy queueing. `git diff --check` passed.
