# Phase 3A ? Beauty Salon Core Domain

Implemented Clients, Stylists, Services, Service Categories, and Salon Preferences. No Stadium or full salon booking workflow was added. Existing clinic domain models and workflows are retained.

## Domain and migration

2026_09_14_100000_create_salon_core.php creates salon_clients, salon_staff_profiles, service_categories, salon_services, salon_staff_branch, salon_service_branch, and salon_service_staff. It adds separate client/stylist sequences on tenants and nullable client_limit/service_limit fields on plans. The migration was applied successfully to the local database.

SalonClient is separate from Patient and belongs to the current tenant and location. Client numbers use CLI-000001 formatting; stylist profile numbers use STY-000001. Number allocation, quota checks, relation writes, and audits occur in a transaction serialized on the tenant row, backed by unique database constraints. Retained archived records count toward client/service quotas.

SalonStaffProfile links to an existing active User and tenant membership. No login credentials or parallel authentication system are created. Profiles can use multiple existing Branch records, but the linked membership must authorize every assigned location. Existing membership/subscription member limits continue to govern staff account provisioning; profiles do not consume a second account seat.

SalonService belongs to a tenant-owned ServiceCategory and supports duration, price, deposit preferences, multiple locations, and multiple assigned stylists. Categories are a shared tenant catalog. Price display uses the shared tenant currency. Commission fields are stored as profile information; no commission payment processing is implemented.

## Access and isolation

SalonAccessService extends the existing ClinicAccessService architecture. All salon endpoints require the exact beauty-salon business type, enabled business capabilities, the relevant plan feature, and module/action permissions. Tenant IDs and client location/number/actor fields are server-owned.

Clients are scoped to the current location. Services and stylists are visible in their assigned current location. A manager must have access to all assigned locations before changing or archiving a shared record. Invalid tenant references, unauthorized locations, inactive staff accounts, mismatched staff location assignments, and stale branch headers are rejected. There are no permanent-delete endpoints.

The new feature flags are clients, salon_staff, and services. Existing plans are not automatically granted these features. Enable the required features in Platform Admin ? Subscription Plans and configure Salon Client/Service limits as appropriate. A null limit follows the existing unlimited-limit convention, while a disabled feature still denies access.

Named permissions: clients.view/create/update/archive; salon_staff.view/manage; services.view/create/update/archive; service_categories.manage; clinic_settings.salon.update. Salon permission selections are exposed in the existing Users / Staff form. Authorization uses permission checks rather than role-name checks.

## API and frontend

All salon APIs use the existing authenticated tenant middleware under /api/v1/salon:

- clients
- stylists
- services
- service-categories

Each supports GET collection, GET /options, POST collection, GET /{record}, and PUT /{record}. Clients and Services also support POST /{record}/archive. Lists have server pagination, search, status filtering, and summary counts. Detail responses provide bounded audit activity without copying personal notes into audit metadata.

Dedicated ClientController, SalonStaffController, SalonServiceController, and SalonServiceCategoryController use a shared small SalonRecordController, centralized validation/persistence/access services, and an explicit SalonRecordResource.

UI routes: /app/clients, /app/stylists, /app/services, and /app/services/categories, each with create/detail/edit routes. Shared pages under pages/salon and SalonFields render entity-specific configuration instead of duplicating forms and tables. The existing API client, clinic context headers, design styles, and terminology helper are reused. Asynchronous list results check tenant/location context and request generation.

The salon sidebar exposes Clients, Stylists, and Services when their plan and permission requirements pass. Appointments remain hidden until booking is implemented. Clinic navigation is unchanged. Client appointment, service, last-visit, and billing history show explicit unavailability rather than fabricated data.

Salon Preferences extends the existing tenant settings schema: default appointment duration, cancellation/deposit policy, walk-ins, and default service tax. Currency remains shared under General and receipt footer settings under Documents. Booking preferences are stored for the future booking workflow.

## Verification

- php artisan test: 97 passed, 1,791 assertions. Existing healthcare regression tests pass.
- SalonCoreTest: 6 passed, 144 assertions; covers CRUD, numbers, archives, audits, categories, multiple stylist assignment, plan limits, business/permission enforcement, tenant/location isolation, options, and protection for records shared across locations.
- Playwright zz_salon.spec.js: 1 passed. Verified linked staff profiles, category/service creation and editing, two assigned stylists, client creation/update/archive, settings, responsive display, and hidden healthcare/booking navigation. No page errors or Vue warnings were observed.
- npm run build: passed. Vite retains a non-blocking main-chunk size warning (~507 kB).
- git diff --check: passed.
- php artisan migrate: applied; migration status confirms the new migration has run.

## Files created

- app/Http/Controllers/ClientController.php
- app/Http/Controllers/SalonRecordController.php
- app/Http/Controllers/SalonServiceCategoryController.php
- app/Http/Controllers/SalonServiceController.php
- app/Http/Controllers/SalonStaffController.php
- app/Http/Resources/SalonRecordResource.php
- app/Models/SalonClient.php
- app/Models/SalonService.php
- app/Models/SalonStaffProfile.php
- app/Models/ServiceCategory.php
- app/Services/SalonAccessService.php
- app/Services/SalonRecordRules.php
- app/Services/SalonRecordService.php
- config/salon.php
- database/migrations/2026_09_14_100000_create_salon_core.php
- resources/js/components/salon/SalonFields.vue
- resources/js/composables/useSalonPage.js
- resources/js/config/salonPages.js
- resources/js/pages/salon/Form.vue
- resources/js/pages/salon/Index.vue
- resources/js/pages/salon/Show.vue
- resources/js/services/salon.js
- tests/Browser/salon-fixtures.php
- tests/Browser/zz_salon.spec.js
- tests/Feature/SalonCoreTest.php
- docs/phase-3a.md (this report)

## Files modified

- app/Http/Controllers/ClinicStaffController.php
- app/Http/Requests/PlanRequest.php
- app/Http/Resources/PlanResource.php
- app/Models/Plan.php
- config/business_types.php
- config/clinic.php
- config/clinic_settings.php
- config/plan_features.php
- resources/js/components/admin/plans/PlanForm.vue
- resources/js/config/planFeatures.js
- resources/js/pages/admin/plans/tabs/LimitsTab.vue
- resources/js/pages/staff/Index.vue
- resources/js/router/index.js
- routes/api.php
