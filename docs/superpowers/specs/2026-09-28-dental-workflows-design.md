# Dental workflows

## Intent and scope
Implement the four requested Dental capabilities: tooth charting, tooth-level history,
procedure catalog/pricing, and multi-visit treatment plans. Reuse patients, doctors,
appointments, tenancy, permissions and the shared invoice/payment ledger.

## Design
Dental-only capability and navigation. Reuse the existing EMR plan entitlement; existing
Dental subscriptions with EMR gain access without a second subscription migration.
Separate permissions: dental.view, dental.chart, dental.procedures.manage,
dental.plans.manage, dental.treatments.complete. Owners/admins retain wildcard access;
doctors receive clinical Dental permissions, not catalog management by default.

Use Universal adult (1–32) and primary (A–T) tooth identifiers, explicit uncharted state, selectable teeth
and surfaces. Findings are append-only; voiding an erroneous finding requires a reason
and retains its author/date. Completed procedures form tooth-level treatment history.
No automatic clinical diagnoses or interpretation of findings.

Tenant-wide catalog: unique code, name, description, default price, active/archive flag.
Plans belong to patient and current branch. Draft plans contain ordered visit numbers
and procedure items with optional tooth/surfaces, quantity, price snapshot and notes.
Draft -> accepted -> completed; cancellation preserves completed items and cancels
remaining work. Only draft plans can be edited. Accepted items can be completed by
an authorized user, optionally linked to an existing appointment for that patient and
branch. Completion is idempotent and records actor/time/notes. All terminal records
are retained. Plan completion follows all items completed.

Plan currency and tax are snapshotted at creation. Completed items can each issue one
invoice through a dental_treatment billing adapter; repeated requests return the same
invoice. Never bill estimates, incomplete items, or accept client totals at issuance.

Patient dental tab: tooth chart, dated findings/history, plan list, plan editor and
visit progress/actions. Dental procedure catalog is a separate sidebar page. Responsive,
keyboard-operable controls; server errors shown; stale tenant/patient responses ignored.

## Integrity and boundaries
Enforce exact Dental business type, operational subscription, EMR entitlement, action
permissions and patient visibility on every endpoint. Clinical records are tenant-owned;
branch records are limited to authorized active branches. Cross-tenant patient, procedure,
appointment and item references fail. Archived patients are read-only. Serialize writes
using the existing tenant billing lock. Validate Universal teeth/surfaces, decimal amounts,
quantities and statuses. Do not hard-delete clinical history. Audit IDs/actions, not notes.

## Verification
Feature tests: capability/permission isolation, catalog validation, immutable chart history,
multi-visit transitions, quote snapshots, branch isolation, archived patient protection,
appointment validation, duplicate completion and invoice issuance. Browser test for chart,
catalog, two-visit plan, treatment history and billing. Run full PHP suite and Vite build.

## Decisions
Work in a feature branch in the shared checkout so the user can run the result directly.
The user's explicit implementation request authorizes implementation; no repeated approval
rounds. No external publication or destructive database operations.
