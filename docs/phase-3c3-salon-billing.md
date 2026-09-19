# Phase 3C.3 — Beauty Salon billing adapter

1. **Existing architecture:** Salon appointments already used `SalonBillingAdapter`, `BillingService`, shared invoice/item/payment models, shared resources and the shared Ledger after Phase 3C.1. Phase 3C.3 extends that adapter instead of rebuilding billing.
2. **Adapter status:** The dedicated adapter existed. It now also owns Salon billing source authorization, limited pending-source discovery and Salon audit context.
3. **Files created:** `app/Http/Resources/SalonBillingSourceResource.php`, `resources/js/components/salon/BillingSources.vue`, `tests/Browser/salon-billing.spec.js`, this report.
4. **Files modified:** `app/Services/Billing/SalonBillingAdapter.php`, `app/Http/Controllers/BillingController.php`, `app/Http/Requests/BillingSourceRequest.php`, `routes/api.php`, `routes/web.php`, `resources/js/pages/salon/Show.vue`, `resources/js/pages/salon/bookings/Index.vue`, `resources/js/pages/billing/Ledger.vue`, `resources/js/composables/useBillingLedger.js`, `tests/Feature/SharedBillingTest.php`, `tests/Frontend/billing-context.test.mjs`, `playwright.billing.config.js`. The web route change keeps unknown GET API paths at 404 and audit logs read-only; the full-suite check exposed the existing SPA fallback issue.
5. **Adapter:** Resolves only authorized Salon appointments and requires `completed`. It passes a normalized draft to `BillingService`. The shared core still owns numbering, limits, persistence, money, payments and audit storage.
6. **Customer ownership:** New Salon invoices use `salon_client_id`, leave `patient_id` null, and store the invoice-time `customer_name`. The shared resource exposes `{type: "salon_client", id, name}`.
7. **Line provenance:** Each selected booked occurrence produces an invoice line with `source_type=salon_appointment_service` and that occurrence's ID. Multiple services remain separate lines.
8. **Snapshots:** Booked service name and price, appointment currency, discount and tax rate are used. Current catalogue or settings changes cannot reprice a completed appointment or a saved invoice.
9. **Reconciliation:** `BillingService` uses `BillingMoney` to allocate discount/tax in cents and verifies subtotal, discount, tax and total against the completed appointment. A mismatch rolls the transaction back.
10. **Idempotency and concurrency:** Repeated POSTs return the existing source invoice. Tenant locking, the source uniqueness constraint and the existing simultaneous-request test protect numbering and duplicates.
11. **Client Billing:** Client profile has a Billing tab using the shared Ledger. The request sends `customer_type=salon_client&customer_id=<client>` to the server and shows only that client's invoices.
12. **Shared ledger:** Salon and Clinic continue to use `BillingInvoice`, `BillingInvoiceItem`, `BillingPayment`, the same invoice/payment API, and `Ledger.vue`.
13. **Cashier access:** `billing.view` plus `billing.create` allows a cashier to list minimal completed, uninvoiced sources in authorized branches and issue them through the existing POST. This does not grant appointment list, details or management. Appointment users keep their existing stylist scope.
14. **Isolation:** Tenant model scopes, authorized branch queries, direct customer validation, source checks and billing `visible()` protect appointments, clients, invoices and payments. The Clinic cannot call the Salon source endpoint.
15. **Tests changed:** Shared billing feature tests cover cashiers, non-completed states, snapshots, audit, history filters, permissions and cross-tenant/branch IDs. Frontend state tests cover Salon Client filter clearing. Browser tests cover Salon invoice/payment/history and cashier discovery.
16. **PHP test result:** `php artisan test --compact` passed: 141 tests, 2,527 assertions. This includes Salon booking/billing, shared billing, Clinic billing, tenant/business isolation and concurrency tests.
17. **Frontend test result:** 5 state tests passed, including Salon Client filter removal when switching businesses.
18. **Browser test result:** 9 browser tests passed. Browser cases use intercepted API responses and built assets; backend behavior is independently covered by feature tests.
19. **Clinic regression:** Existing Clinic tests and browser flow passed. Clinic adapter and financial behavior were not changed.
20. **Build:** `npm run build` completed successfully.
21. **Git:** The final status is recorded in the completion response. There is no schema migration; existing invoices, payments, source IDs and sequences are untouched.
22. **Remaining issues:** The browser checks use mocked HTTP rather than a live server; backend endpoint behavior is covered by feature tests. Receipts, PDFs, Products, Inventory, POS and Phase 3C.4 are outside this phase.

The appointment UI shows Create Invoice only for completed uninvoiced Salon appointments, then View Invoice through the shared billing detail. Salon billing works with `clinical=false`. The Salon adapter prepares Salon-specific charges; generic finance remains in the shared core.
