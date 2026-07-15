# Data dictionary

| Table | Purpose | Important invariants |
|---|---|---|
| `ct_clients` | Borrower identity and contact details | Optional national ID unique; deactivation preserves history. |
| `ct_client_documents` | Protected document metadata | Random stored name; content is not stored in audit logs. |
| `ct_loans` | Loan terms and running totals | Loan number and idempotency key unique; decimal money. |
| `ct_schedules` | Contractual installments | `(loan_id, installment_number)` unique; portions reconcile to loan total. |
| `ct_payments` | Immutable receipt ledger | Idempotency key unique; reversals link to originals. |
| `ct_payment_allocations` | Payment-to-installment mapping | One allocation per payment/schedule pair. |
| `ct_audit_logs` | Append-only application activity | No application update/delete path. |
| `ct_jobs` | Backup/export job metadata | Paths are protected references. |
| `ct_notifications` | Per-user operational notices | User/notification key prevents duplicates. |
