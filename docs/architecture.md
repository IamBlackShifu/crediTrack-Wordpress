# CrediTrack architecture and policy register

## Product-policy defaults

These defaults are explicit and configurable where noted. Changing a lending default never recalculates an existing loan.

| Decision | Version 1 policy |
|---|---|
| Tenancy and branches | Single tenant, single branch. No branch-scoped access. |
| Currency | One ISO 4217 currency per installation; USD default; two decimal places. |
| Flat interest | `principal * annual_rate / 100`, independent of term (legacy product interpretation). |
| Monthly interest | Simple interest: `principal * rate / 100 * term_months`. |
| Quarterly interest | Simple interest: `principal * rate / 100 * term_months / 3`. |
| Annual interest | Simple interest: `principal * rate / 100 * term_months / 12`. |
| Rounding | Half-up to currency precision. Schedule remainder goes to the final installment. |
| Month end | Add calendar months while clamping to the final valid day of the target month. |
| Overpayment | Rejected. No unallocated client-credit ledger in version 1. |
| Approval | Administrators may always approve/disburse. Officers may do so when enabled; maker-checker is not enforced. |
| Reversal | Administrator only, mandatory reason, compensating negative payment plus restored allocations. |
| Retention | Application uninstall preserves data unless an administrator explicitly enables deletion. Local law determines the actual retention period. |
| Notifications | In-portal due/overdue indicators. Email/SMS/WhatsApp are out of scope until a delivery policy is selected. |

## Packages

- `creditrack-core`: domain/application services, custom tables, authorization, handlers, scheduled work, exports and operations.
- `creditrack-portal`: server-rendered accessible portal. Production does not require Node.js.

## Tables

All tables use the WordPress prefix plus `ct_`: clients, client_documents, loans, schedules, payments, payment_allocations, audit_logs, jobs, idempotency, and notifications. WordPress options hold the singleton settings document and schema version. Monetary columns use `DECIMAL(18,2)`.

## Capabilities

| Capability group | Administrator | Loan Officer | Viewer |
|---|:---:|:---:|:---:|
| Read portal/clients/loans/payments | Yes | Yes | Yes |
| Manage clients/documents | Yes | Yes | No |
| Create loans / record payments | Yes | Yes | No |
| Approve/disburse | Yes | Policy | No |
| Reports | Yes | Yes | No |
| Reverse payments | Yes | No | No |
| Users/settings/audit/backups | Yes | No | No |

Authorization is checked in application services and request handlers, not only in the UI.

## Loan state machine

`Pending -> Approved -> Active -> Completed`; `Pending|Approved|Active -> Defaulted`; `Defaulted -> Written Off`. Payments are accepted only for Active or Defaulted loans. Completion is automatic only when total repayable is fully settled.

## Allocation and concurrency

Posting a payment starts a database transaction and locks the loan and its unpaid schedules. The service rejects duplicates using a unique idempotency key, validates outstanding balance, inserts the immutable payment, allocates oldest due schedules first, updates schedule and loan totals, and appends an audit event before commit.

## Migration strategy

Activation and upgrades run ordered, idempotent migrations through `dbDelta()`. The schema version advances only after a migration succeeds. Before production updates, take a host-level backup. Deactivation retains all data; uninstall retention is the default.
