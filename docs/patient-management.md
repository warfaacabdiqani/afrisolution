# Patient Management

Patient Management is implemented within the existing clinic application. The list, registration, profile, editing, archive/restore, baseline medical history, private documents, and activity screens are available. Scheduling, consultations, EMR, pharmacy, billing, and reports remain separate future stages.

## Data and access model

Patients belong to the tenant. `registration_branch_id` records where they were registered; patient identity is shared across authorized branches in that tenant. Every API operation first verifies the authenticated membership, valid branch context, subscription, patient-management plan feature, and applicable permission through the existing `ClinicAccessService`. Patient and child queries use the existing fail-closed `BelongsToTenant` scope. Child resources are also resolved through their parent patient. Composite foreign keys enforce tenant ownership at the database level.

Normal workflows archive rather than hard-delete records. All retained patients, including archived and inactive records, consume the patient quota. Archived documents remain private and consume storage quota. Registration and document uploads serialize quota checks on the tenant row. Patient numbering increments a tenant-specific sequence within the same transaction, backed by a unique tenant/patient-number index; it never uses `count() + 1`.

Gender accepts only Male and Female. Birth information supports an exact DOB or a reported age of 0–130 years. Age-only entries store the reported age and its recording date, leaving DOB null. The displayed age is marked as estimated; subsequent edits preserve the original baseline when the reported current age has not changed. Exact DOB and reported age cannot be supplied together.

Duplicate warnings are scoped to the tenant and use phone, email, or first/last name plus exact DOB. A user can review the matching record or explicitly continue registration. Similar names alone do not block registration.

## Created files

- Migrations: `2026_09_07_060000_create_patient_management.php`, `2026_09_07_070000_add_reported_patient_age.php`.
- Models: `Patient`, `PatientAllergy`, `PatientCondition`, `PatientDocument`. Patient has allergies, conditions, documents, and registration-branch relationships; documents expose an uploader relationship.
- Backend: `PatientController`, `PatientHistoryController`, `PatientDocumentController`, `PatientRequest`, `PatientResource`, `PatientService`.
- Frontend pages: `pages/patients/Index.vue`, `FormPage.vue`, `Show.vue`, and overview, medical-history, documents, activity, and future-workflow tabs.
- Components: `PatientForm.vue`, `PatientTable.vue`, `PatientStatus.vue`, `PatientActions.vue`, `HistoryPanel.vue`.
- State/API: `stores/patients.js`, `services/patients.js`.
- Styling: `resources/css/patients.css`.
- Tests: `tests/Feature/PatientManagementTest.php`.

Modified integration points include API and Vue routes, clinic permission configuration, platform membership permission validation, private filesystem configuration, clinic layout navigation behavior, dashboard API/UI, CSS imports, existing foundation tests, and browser workflow coverage.

## APIs

All patient endpoints are under `/api/v1/clinic/patients` and reuse authenticated tenant middleware.

| Method | Suffix | Purpose |
| --- | --- | --- |
| GET | `/` | Search, filters, sort, pagination, and real summary statistics |
| POST | `/` | Validate/register; return 409 with scoped possible duplicates when applicable |
| GET | `/{patient}` | Patient profile; medical fields only with medical-history permission |
| PUT | `/{patient}` | Update mutable patient information |
| POST | `/{patient}/archive`, `/{patient}/restore` | Retain and change record status |
| GET / POST | `/{patient}/allergies`, `/{patient}/conditions` | Paginated history and new entries |
| PUT | `/{patient}/allergies/{entry}`, `/{patient}/conditions/{entry}` | Edit/deactivate allergy or update condition status |
| GET / POST | `/{patient}/documents` | Private document metadata and upload |
| GET | `/{patient}/documents/{document}/download` | Authorized attachment download |
| POST | `/{patient}/documents/{document}/archive` | Archive document without discarding the retained file |
| GET | `/{patient}/activity` | Paginated operational activity with human-readable UI labels |

The list supports name tokens, patient number, phone, email, gender, status, blood group, registration date range, name/number/registration/status sorting, and 25/50/100 rows per page. List responses omit medical history, notes, emergency contacts, and document content. The dashboard now shows real active patient counts and five recent active patients when authorized.

## UI routes

`/app/patients`, `/app/patients/create`, `/app/patients/:id/edit`, and `/app/patients/:id` (redirecting to overview) use dedicated pages. Profile tabs are route-driven: overview, medical-history, documents, activity, and clearly unavailable future workflows. Mobile lists use patient cards and forms become single-column. Search/filter state is retained in URL queries without interrupting keyboard focus.

## Permissions and privacy

Permissions: `patients.view`, `patients.create`, `patients.update`, `patients.archive`, `patients.restore`, `patients.medical_history.view`, `patients.medical_history.update`, `patients.documents.view`, `patients.documents.upload`, and `patients.documents.delete`.

Receptionists retain demographic access but cannot retrieve medical history or documents without explicit grants. Doctors and nurses receive the configured medical-history permissions; clinic owners/admins retain their existing wildcard clinic grants. No clinic permission grants platform access.

Documents use the `patient_private` disk under `storage/app/patient-private`, outside public storage, with direct serving disabled. Uploads validate actual MIME, extension, successful upload, and a 10 MB per-file limit. Allowed formats are PDF, JPG/JPEG, and PNG. Paths are never returned by document APIs. Downloads require current authorization and use attachment disposition, private/no-store headers, and MIME sniffing protection.

Existing audit logging records patient create/update/archive/restore, document upload/archive, and allergy/condition create/update events. Metadata contains patient and branch IDs, not diagnoses, notes, file content, or demographic values. Activity filters medical/document events according to the caller's permissions.

## Verification

The patient feature tests cover validation, numbering, duplicate detection, tenant isolation, child ownership, searching, pagination, sorting, status transitions, quota/feature/subscription restrictions, valid branch membership, restricted medical reads/writes, private document upload/download, audit privacy, and DOB/age rules. Existing platform and clinic tests remain in the full suite.

Browser coverage exercises login, clinic selection, patient listing, registration, profile navigation, editing, conditions, private upload/download, search, mobile cards, archive/restore, refresh, gender options, age-only entry, and explicit duplicate confirmation. Screenshots are saved to `test-results/patients-desktop.png`, `patients-mobile.png`, and `patient-age-form.png`.

Run with this workspace's PHP 8.4 executable:

```powershell
& C:/php84/php.exe artisan migrate --force
& C:/php84/php.exe artisan test
npm run build
npm run test:browser
```

No example patients are inserted into the application's database; browser fixtures and test records use the dedicated test database.
