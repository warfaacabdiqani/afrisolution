# Shared Business Deposits Design

## Purpose

Allow Clinic, Dental, and Beauty Salon businesses to collect a deposit before service completion, issue a receipt immediately, and carry that deposit into the shared billing lifecycle without creating the final service invoice early. The workflow uses Afriso's existing billing permissions, payment methods, numbering, tenant isolation, and reports.

## Existing system and scope

Salon services already have `requires_deposit` and `deposit_amount`. Clinic appointments resolve a consultation fee from the doctor or clinic setting. Dental treatment-plan items snapshot procedure prices. These fees become final invoices only after completion, and payments and receipts can only exist against an invoice.

Stadium has no facility, customer, reservation, or charge source in the application. The shared foundation will accept a future Stadium adapter, but this task will not create orphan payments or invent a Stadium booking system. Stadium deposit endpoints must fail closed until a real reservation source exists.

## Selected architecture

Use a separate shared-ledger **deposit invoice** instead of creating the final service invoice early or adding an unrelated cash table. Deposit invoices use registered source adapters, existing customer and branch ownership, payment recording, idempotency, receipt generation, numbering, reports, and access controls.

Deposit source types are `clinic_appointment_deposit`, `dental_plan_deposit`, and `salon_appointment_deposit`. Completed-service source types remain unchanged. For a USD 50 charge with a USD 10 deposit, the ledger contains a USD 10 deposit invoice and later a USD 40 final invoice. Together they represent USD 50 invoiced and a collected USD 10 deposit leaves USD 40 outstanding.

## Configuration by business type

- **Beauty Salon:** preserve each service's existing deposit configuration and the booking's capped deposit snapshot.
- **Clinic:** appointment types gain deposit mode (`none`, `fixed`, or `percentage`) and value. Clinic billing settings provide a default when the selected appointment type has no override. The appointment snapshots the requirement from its resolved consultation fee.
- **Dental:** the treatment plan gains deposit mode and value. Its requirement is calculated and snapshotted when the plan is accepted, based on the accepted plan total.
- **Stadium:** the common adapter contract and fail-closed capability are present, but no collection UI or endpoint is exposed without a reservation source.

Fixed deposits are capped at the source total. Percentages accept 0–100 and use the existing money-rounding rules. Once a deposit invoice exists, the required amount and its commercial basis are immutable.

## Collection flow

1. The source record snapshots its fee basis and required deposit.
2. Its detail screen shows required, invoiced, collected, refunded, and remaining amounts.
3. `Collect Deposit` uses enabled shared-billing payment methods.
4. Submission atomically creates or reuses the single deposit invoice and records a payment.
5. Existing idempotency prevents duplicate collection during retries and concurrent submissions.
6. An immutable shared receipt is issued immediately and is printable from the source and billing screens.
7. Collection cannot exceed the deposit invoice balance. Partial collection follows the existing `partial_payments` setting. Zero-deposit records expose no collection control.

## Final invoices and allocation

The existing explicit final-invoice actions remain. A final invoice calculates its normal subtotal, discount, and tax first, then applies a non-taxable post-tax credit named `Deposit previously invoiced` for the applicable non-void deposit amount. This prevents double billing without incorrectly reducing tax and retains the deposit invoice and receipt.

Clinic and Salon each allocate the deposit once to their completed source. Dental allocates the plan deposit across completed procedure invoices in completion order; each procedure consumes the smaller of its gross charge or the plan's unallocated deposit. An immutable deposit-allocation row connects each deposit invoice to each final invoice and records the amount consumed.

If no deposit invoice exists, the final invoice remains the full charge. If a deposit invoice is partly unpaid, its outstanding balance remains on that invoice while the final invoice covers the rest. Every adapter validates tenant, branch, customer, currency, source, and arithmetic reconciliation.

## Editing and rescheduling

Rescheduling keeps the same appointment or booking and deposit invoice. Dental scheduling changes do not affect its plan deposit.

After a deposit invoice is issued, patient/client, services/procedures, quantities, discount, tax, fee, and other price-bearing fields are locked. Date, time, assigned provider within authorized scope, notes, and ordinary status transitions remain editable. This prevents mutable operational data from changing an immutable financial snapshot.

## Cancellation, refunds, and forfeiture

Cancelling the source with an unpaid deposit invoice automatically voids that invoice. Cancelling after any collection requires an explicit disposition:

- **Forfeit:** retain the invoice and receipt and audit the disposition and reason.
- **Refund:** create idempotent refund entries against the original deposit payments, reduce net collections, issue immutable refund receipts, mark the source disposition, and then cancel.

Refunds use the original payment method and never delete or mutate original payments or receipts. A refund cannot exceed the unrefunded amount. Refund support in this scope is limited to deposit cancellation; it is not a general invoice-refund feature.

## Data model

Add the minimum shared fields:

- `billing_payments.type`: `payment` by default or `refund`.
- `billing_payments.reverses_payment_id`: nullable self-reference.
- `billing_invoices.credit`: non-negative post-tax credits, default zero; invoice total is subtotal minus discount plus tax minus credit.
- `billing_invoice_items.kind`: `charge` by default or `credit`, allowing the document to explain the post-tax deposit adjustment.
- A deposit-allocation table linking deposit and final invoices with an immutable amount.
- Consistent deposit requirement, mode/value where relevant, disposition, timestamp, and actor fields on Clinic appointments, Dental plans, and Salon appointments.
- Deposit mode/value fields on appointment types and Clinic billing settings; Dental plan fields; Salon retains service fields.

No second invoice, payment, receipt, tenant, customer, or numbering system is introduced. Existing payment rows receive the safe `payment` default. Each source exposes final and deposit invoice relationships, and API resources return server-computed summaries rather than browser totals.

## Permissions and isolation

- Viewing deposit state follows source visibility.
- Creating the deposit invoice requires `billing.create` and source-workflow access.
- Collecting requires `billing.payments`.
- Refund or forfeiture requires `billing.payments` plus the source cancellation permission.
- Every lookup is restricted to the active tenant and authorized branch.
- Existing plan features and business capabilities remain required.

## UI and reports

Clinic appointment, Dental treatment-plan, and Salon booking details gain a compact Deposit section. It shows collection only when money remains and the user is authorized. Successful collection refreshes the source and exposes the receipt. Cancellation asks for Refund or Forfeit only when money was collected.

Invoice and receipt screens label each deposit source clearly. Shared reports include deposit invoices in invoiced totals, payments in collections, and refunds as reductions to net collections. Optional business-type deposit breakdowns must reconcile with the existing currency totals. All forms remain usable on compact desktop and mobile layouts.

## Error handling and concurrency

- Repeated deposit-invoice creation returns the existing invoice.
- Matching payment/refund idempotency retries return the existing result; conflicting reuse returns HTTP 409.
- Tenant billing locks and database uniqueness protect invoice and receipt numbers.
- Concurrent collections cannot overpay.
- Final invoicing and cancellation lock the source, deposit invoice, and allocation state before calculation.
- Failed invoice, payment, refund, receipt, or allocation operations roll back together.

## Backward compatibility

Existing sources, invoices, payments, and receipts remain valid. No historical deposit is inferred. Existing eligible Salon bookings retain their stored requirement. Existing Clinic appointments and Dental plans default to no deposit. Final billing stays unchanged except for explicit deposit adjustments.

No payment gateway, stored card, wallet, general credit-note system, Stadium booking system, or manual orphan deposit is added.

## Verification

Feature tests cover Clinic, Dental, and Salon configuration and calculation; collection and receipts; partial payments; retries; concurrency; isolation; permissions; rescheduling; financial locks; unpaid cancellation voiding; refund and forfeiture; refund idempotency; single- and multi-invoice allocation; reports; migration safety; existing billing regression; and Stadium fail-closed behavior.

Browser tests cover configuration, detail summaries, collection, receipts, cancellation disposition, failures, and compact layouts. Verification includes focused tests, the complete PHP suite, frontend tests, browser tests, `npm run build`, route inspection, migration checks, and `git diff --check`.
