# Phase 3C.4 — Shared Payments & Receipts

1. **Existing architecture:** Clinic and Beauty Salon already used `BillingInvoice`, `BillingPayment`, and one `BillingService`. Payments were tenant-scoped, branch-authorized through their invoice, balance-checked, and idempotent.
2. **Payment changes:** A successful payment now issues a receipt in the same database transaction. Reused idempotency keys reject changes to invoice, amount, method, or reference.
3. **Receipt architecture:** One shared `BillingReceipt` belongs to one payment and one invoice. A tenant/payment unique key enforces one receipt per payment; no Clinic or Salon receipt/payment model was introduced.
4. **Migration:** `2026_09_19_100000_add_shared_billing_receipts.php` adds the receipt table, tenant receipt sequence, and nullable invoice document identity snapshot. It preserves existing invoices, payments, and invoice sequences.
5. **Numbering:** Each tenant has a separate locked `billing_receipt_sequence`. Numbers use its configured `receipt_prefix` and `number_length`; uniqueness is enforced per tenant. Changing a prefix cannot reuse a number already issued.
6. **Snapshots:** Receipt JSON contains explicit scalar business and branch identity/contact, Patient or Client label, customer and invoice names/numbers, amount, method, reference, UTC payment time, invoice total, previous paid amount, balance after payment, and recorder name. New invoices snapshot the business identity used on invoice printouts. Financial invoice items remain the existing immutable snapshots.
7. **Historical payments:** No receipt is fabricated for payments recorded before this migration, because payment-time business identity and prior balance cannot be reconstructed reliably. They remain in the ledger with “Receipt unavailable (historical payment).” The receipt endpoint returns 404 for them. Older invoices may print using current business details with a visible warning.
8. **Invoice print:** `/app/billing/invoices/:id/print` uses the shared invoice API, item snapshots, Patient/Client terminology, and browser Print / Save PDF.
9. **Receipt print:** `/app/billing/payments/:id/receipt` uses the issued receipt snapshot. A4 print CSS hides navigation, top bar, controls, and unrelated UI. Receipt rendering only reads records.
10. **Ledger:** Shared invoice details link to invoice print. Payment history shows receipt number, recorder, and View Receipt; the payment form shows total, already paid, and balance.
11. **Permissions:** Reading either document requires shared `billing.view` access; recording payments requires `billing.payments`. There is no business-specific receipt permission.
12. **Plan features:** The existing `billing` plan feature remains the active billing gate. Granular `invoices`, `payments`, and `receipts` feature flags are not newly enforced, preserving existing plans.
13. **Isolation:** APIs use tenant-scoped models and authorized branch-filtered invoices. Tests cover foreign tenant, restricted branch, and missing view permission. The print component clears state and aborts requests on context changes.
14. **Idempotency:** An exact retry returns the existing payment and receipt without incrementing either sequence. Conflicting reference and other payment identity fields return 409.
15. **Concurrency:** `BillingLock` serializes payment transactions per tenant; balance is recalculated inside the transaction. A two-process test confirms only one full-balance payment and receipt commit.
16. **Audit:** Existing `billing.payment.recorded` remains; new issuance records `billing.receipt.created`. Read-only reprints do not add audit noise.
17. **Tests changed:** Shared billing feature tests cover receipt issuance, partial/final payments, snapshots, reprints, conflicts, access, migration preservation, and a real concurrent payment race. Shared browser tests cover invoice/receipt display and print-media navigation hiding for Clinic and Salon.
18. **PHP:** `C:/php84/php.exe artisan test --compact` passed, 146 tests and 2,610 assertions, including the final migration and branch-access checks.
19. **Frontend tests:** Billing UI behavior is exercised by the Playwright browser suite; this repository has no separate billing unit-test script.
20. **Browser:** `npx playwright test --config=playwright.billing.config.js` passed, 12 tests.
21. **Build:** `npm run build` passed after the UI changes.
22. **Git:** All Phase 3C.4 changes remain in the working tree for review; no commit was created. `git diff --check` passed.
23. **Limitations:** Refunds, reversals, gateways, Pharmacy, Inventory, Products, POS, and advanced financial reports remain outside this phase. No payment or receipt delete API was added.

Clinic and Beauty Salon use **one payment and receipt architecture**. Each newly committed payment gets exactly one receipt; partial payments get separate receipts; reprinting creates none. Issued receipt snapshots retain historical identity and money values, with Patient language for Clinic and Client language for Salon. Salon billing works without clinical capability. Existing invoice and payment flows remain in place.
