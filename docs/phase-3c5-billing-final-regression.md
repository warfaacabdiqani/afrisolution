# Phase 3C.5 — Billing Reports and Final Regression

1. **Files:** Added `BillingReportController`, `BillingReportService`, a payment-date index migration, and the shared `Report.vue` page. Updated billing routes/API, ledger and Reports links, the legacy financial report, and Clinic/Salon feature and browser tests.
2. **Endpoint:** `GET /api/v1/billing/reports/summary?from=YYYY-MM-DD&to=YYYY-MM-DD&branch_id=all|ID&page=N` returns filters, business name, per-currency totals/statuses/methods, and a 50-row paginated invoice table. The old `/api/v1/clinic/reports/financial` endpoint now adapts the same ledger service for the existing report UI.
3. **Calculations:** Invoiced is the sum of issued invoice totals; collected is the sum of recorded payments; outstanding is the current `total - paid` for invoices issued in the selected period. Counts use existing `paid`, `partial`, and `unpaid` statuses. Database `SUM`, `COUNT`, and `GROUP BY` perform the aggregations; appointment fees never enter financial totals.
4. **Dates:** Invoice figures use `issued_at`, falling back to `created_at` for historical invoices without an issue timestamp. Collections use `paid_at`. Calendar boundaries are converted from the tenant timezone to UTC for querying; displayed invoice dates use the tenant timezone. The default range is the current tenant-local month through today.
5. **Branches:** The default includes all authorized branches; an explicit branch must be among the user's authorized branches. Every invoice and payment query is scoped to those branches. The selector is hidden when the user has one accessible branch.
6. **Currency:** Each currency has separate invoiced, collected, outstanding, status, and method figures. There is no conversion or cross-currency total.
7. **Printing:** The shared Billing Report page prints its currently displayed invoice page with the full-period summary, report period, business name, and branch context. A4 landscape print CSS hides navigation, filters, and buttons. Browser Print / Save PDF is used.
8. **Estimated reporting:** The old financial endpoint no longer claims appointment-fee estimates are revenue. Clinical overview and doctor/branch operational reports identify any remaining consultation-fee estimates as estimates. Clinical activity reports stay available.
9. **Permissions:** `billing.view` and the existing billing business capability and plan feature gate the report. Salon does not need clinical capability or a healthcare appointment API.
10. **Isolation:** Tenant ID comes from authenticated context. Queries explicitly scope tenant and authorized branch; foreign tenant and branch requests are rejected or excluded. The page clears report state and aborts requests on context changes.
11. **Tests:** Added focused Clinic and Salon report tests for real invoices, payments, receipts, counts, methods, local date boundaries, currencies, unauthorized branches, foreign tenants, permissions, and the legacy financial endpoint. Browser tests cover the shared report and print media for both businesses.
12. **PHP:** `C:/php84/php.exe artisan test --compact` passed: 149 tests, 2,681 assertions.
13. **Frontend:** UI behavior is exercised in the billing Playwright suite; the repository has no separate billing unit-test script.
14. **Browser:** `npx playwright test --config=playwright.billing.config.js` passed: 12 tests.
15. **Build:** `npm run build` passed.
16. **Limitations:** Invoice rows are paginated at 50; printing shows the current page and states its range, while totals cover the whole filter. Outstanding is the current balance of period-issued invoices, not a historical as-of balance. Refunds/reversals, accounting statements, exports, gateways, Pharmacy, and Inventory/POS remain outside this phase.

The Clinic and Beauty Salon invoice, payment, receipt, and printable document flows continue to share one billing ledger. Phase 3C.5 adds reporting to that ledger without another accounting system.
