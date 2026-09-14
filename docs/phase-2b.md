# Phase 2B: business-aware dashboard

The existing /api/v1/clinic/dashboard endpoint and dashboard store are retained. Phase 2B stops at dashboard composition; no Salon, Stadium, or Dental operational workflows were added.

## Architecture and response

config/dashboard.php defines widget metadata, profile composition, supported sections, and implemented quick actions. BusinessProfileService resolves these through the existing dashboard_profile_key. Dental continues to use the clinic profile. Business terminology, including workspace and information-card labels, stays in config/business_types.php.

DashboardProfileService applies the existing business-module, plan-feature, and permission results. It uses aggregate counts, bounded recent-record queries, and eager-loaded appointment relations. Salon and Stadium dashboards do not query patients, doctors, appointments, consultations, or prescriptions.

The API provides profile, business, widgets, sections, quick_actions, and today. Widgets carry key, label, value, format, availability, icon, tone, and currency metadata. Legacy care response fields remain for healthcare consumers; non-healthcare responses omit them.

Clinic and Dental retain actual patient counts, today's appointments, active clinicians, recent patients, and calendar data. Salon and Stadium use active staff in the current location, authorized locations, and the current plan. Revenue is null/unavailable because invoice and payment tables do not exist. No appointment-based revenue estimate is used.

## Frontend and switching

Dashboard.vue composes DashboardHeader, DashboardStatsGrid, DashboardStatCard, DashboardSection, QuickActions, and BusinessInformation. It renders available API sections without business-type branches. Existing teal styling, cards, responsive grids, and generic trial banner are retained.

Quick actions include implemented patient registration, appointment booking, and prescription creation when all access layers allow them. Invoice creation was a placeholder and is excluded. No unfinished Salon or Stadium actions appear.

Tenant selection clears dashboard data and invalidates pending requests immediately. The dashboard store checks request generation and tenant identity before accepting a response, preventing old business metrics from replacing the active dashboard.

## Files

- app/Http/Controllers/ClinicDashboardController.php
- app/Services/BusinessProfileService.php
- app/Services/DashboardProfileService.php (new)
- config/business_types.php
- config/dashboard.php (new)
- resources/js/pages/clinic/Dashboard.vue
- resources/js/components/dashboard/DashboardHeader.vue (new)
- resources/js/components/dashboard/DashboardStatsGrid.vue (new)
- resources/js/components/dashboard/DashboardStatCard.vue (new)
- resources/js/components/dashboard/DashboardSection.vue (new)
- resources/js/components/dashboard/QuickActions.vue (new)
- resources/js/components/dashboard/BusinessInformation.vue (new)
- resources/js/stores/auth.js
- resources/js/stores/clinicDashboard.js
- tests/Browser/business.spec.js
- tests/Feature/BusinessModuleAccessTest.php
- tests/Feature/ExampleTest.php
- tests/Feature/SpaRoutingTest.php
- docs/phase-2b.md (this report)

The shell tests now initialize their database because the current branded application shell reads system settings. Browser checks use the current login label and authenticated JSON request headers.

## Verification

- php artisan test: 91 passed, 1,647 assertions. Existing clinic regression tests pass.
- Focused dashboard/access tests: 6 passed, 205 assertions.
- Playwright business suite: 2 passed. Covers Clinic ? Salon ? Stadium ? Clinic ? Dental, widgets, information cards, settings/navigation, direct denial, backend denial, and platform profile visibility. No page errors or Vue warnings were recorded.
- npm run build: passed. Vite reports a non-blocking main-chunk size warning (~505 kB).
- git diff --check: passed.

No further development phases were started.
