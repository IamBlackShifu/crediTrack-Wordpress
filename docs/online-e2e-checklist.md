# Online staging E2E checklist

Record the date, release version, WordPress/PHP/database versions, webserver, cache/CDN, tester, and evidence for every run. Use synthetic clients and documents only.

## Infrastructure and public boundary

- HTTPS redirects correctly; cookies are Secure and HttpOnly; mixed-content scan is clean.
- Anonymous users cannot access `/creditrack/dashboard/`, records, exports, documents, backups, or `admin-post.php` actions.
- `/wp-content/creditrack-private/` and guessed children return 403/404.
- Authenticated portal and download responses are not stored by page cache, proxy, or CDN.
- PHP errors are hidden from browser responses and safely available to administrators in server logs.
- SMTP password-reset delivery works; SPF/DKIM/DMARC results are acceptable.
- Real cron runs overdue/notification refresh and is monitored.

## Installation and upgrade

- Fresh activation creates all `ct_` tables, roles, capabilities, settings, and scheduled event once.
- Upgrade from the previous release preserves users, loans, schedules, payments, documents, audit records, and settings.
- Deactivation/reactivation preserves data. Uninstall preserves data by default.
- `tests/live-diagnostic.php` reports the expected schema and no forced-password or arrears anomalies.

## Authentication and authorization

- Administrator, Loan Officer, and Viewer land on the correct portal and see only permitted navigation/actions.
- A new user must change the temporary password, reaches the dashboard afterward, and can sign in with the new password only.
- Inactive users and repeated invalid-login attempts are blocked with generic errors.
- Viewer mutation attempts made through forged POST requests fail with 403 and make no database changes.
- Loan Officer forged access to users, settings, audit, backups, approval/disbursement outside policy, and reversals fails.
- Session lock, sign out, password reset, password change, and account deactivation invalidate the intended sessions.

## Critical business workflow

- Configure company, currency, timezone, interest basis, grace period, notification window, permissions, upload limit, and retention.
- Create, edit, search, and deactivate a synthetic client; confirm open-loan deactivation protection.
- Upload valid PDF/JPEG/PNG documents; reject spoofed MIME, wrong extension, oversized, executable, and unauthorized requests.
- Create a loan and verify preview equals the server result; approve and disburse only through valid state transitions.
- Reconcile every schedule row, final rounding adjustment, total interest, total repayable, and maturity date.
- Post partial, exact-final, advance/multi-installment, excessive, negative, duplicate, and concurrent payments.
- Verify the USD 100 principal / USD 115 repayable / USD 100 paid case remains Active with USD 15 outstanding.
- Reverse a payment with an authorized account and mandatory reason; verify compensating ledger, schedule, balance, and audit records.
- Move staging time forward or use fixtures to verify grace days, days past due, dashboard risk, notification badge/inbox, and deduplication.

## Reports, exports, and reconciliation

- Reconcile dashboard figures to loan, schedule, and payment rows.
- Run each report with empty, normal, boundary, and reversed-payment data plus date filters.
- Open CSVs safely; values beginning with `=`, `+`, `-`, `@`, tab, or carriage return cannot execute formulas.
- Test realistic high-volume report/export size for timeout and memory behavior.
- Verify responsive layout, keyboard navigation, visible focus, labels, errors, reduced motion, printing, and supported browsers/mobile devices.

## Backup, restore, and recovery

- Create and download an application backup; validate manifest and checksums.
- Reject a corrupt, altered, oversized, unauthorized, or incompatible archive without losing current data.
- Restore a valid synthetic backup and reconcile settings, all tables, documents, and audit trail.
- Complete a host-level database/files restore exercise and record recovery time and recovery point.

## Promotion gate

Do not enter real client data until all critical items pass, no unresolved PHP/database errors remain, authorization tests produce no data changes, reconciliation differences are zero, and rollback plus disaster recovery have been demonstrated. Record accepted non-critical defects with owner and target date.
