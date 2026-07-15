# CrediTrack WordPress Rebuild — Comprehensive Handoff Prompt

Copy everything below the **Prompt starts here** heading into a new development session. The target is a web-based rebuild of the current CrediTrack desktop application with complete functional parity.

## Important architectural note

Do not implement the lending system inside a WordPress theme alone. Build:

1. **CrediTrack Core**, a custom WordPress plugin that owns database tables, lending rules, permissions, REST endpoints, scheduled jobs, files, reports, audit logs, backup/export logic, activation, upgrades, and uninstall behavior.
2. **CrediTrack Portal**, a custom WordPress theme that owns the responsive application interface, layouts, dashboards, forms, tables, charts, print styles, and branding.

The plugin must continue working and retain all data if the theme is changed. Avoid WooCommerce, membership, form-builder, loan-management, or page-builder plugins for core behavior.

---

# Prompt starts here

You are a senior WordPress solutions architect, PHP engineer, database designer, security engineer, and financial-software QA lead. Build a production-quality web version of **CrediTrack**, a single-tenant microfinance and credit-management system. It must reproduce all functions specified below end to end.

Do not stop at mockups, static templates, placeholder buttons, hard-coded statistics, or partial CRUD. Implement working database persistence, server-side authorization, validation, calculations, workflows, reports, notifications, tests, installation, upgrades, and documentation.

## 1. Required solution architecture

Create two installable packages in one development repository:

- `creditrack-core/`: custom WordPress plugin containing all domain and application logic.
- `creditrack-portal/`: custom WordPress theme containing the user interface.

Use current stable WordPress and supported PHP versions. Follow WordPress Coding Standards and use namespaced, object-oriented PHP with dependency separation. Use Composer for PHP development tooling and npm only where needed for compiled UI assets. Prefix all PHP symbols, hooks, options, REST namespaces, scripts, styles, cron events, and database tables with `creditrack`/`ct_` to prevent collisions.

Prefer a server-rendered WordPress application with progressively enhanced JavaScript, or a React-powered portal backed by the WordPress REST API. Whichever approach is chosen, it must be accessible, responsive, fast on ordinary shared/VPS hosting, and must not require Node.js in production.

Use dedicated custom database tables via `dbDelta()` rather than posts and post meta for transactional lending data. Store monetary amounts as fixed-point `DECIMAL`, never floating point. Use database transactions and row locking or an equivalent concurrency strategy for approval, disbursement, and payment operations. Store all timestamps consistently in UTC and display them in the configured WordPress/company timezone.

## 2. Users, authentication, and roles

Use WordPress users and authentication, but create these application roles and explicit capabilities:

- **CrediTrack Administrator**: full access to dashboard, clients, loans, payments, reports, users, audit logs, backups, and settings.
- **Loan Officer**: view/create/edit clients; upload/manage client documents; create loans; approve and disburse loans if the configured policy permits; record repayments; view operational reports; edit own profile. No global settings, backup/restore, or user administration.
- **Viewer**: read-only dashboard, client records, loan lists/details, repayment history, and other explicitly permitted read-only pages. A Viewer must never create, edit, approve, disburse, repay, deactivate, or delete anything, including client accounts.

Implement authorization on every server-side handler and REST endpoint; hiding a button is not security. Use WordPress nonces for state-changing browser requests, capability checks, secure cookies, login throttling, session invalidation after password or account status changes, and generic login errors. Inactive users cannot log in. Record the last successful login time.

Provide an administrator-only users page supporting search, list, create, edit name/email/role, activate/deactivate, and secure password reset. Prevent an admin from accidentally removing the final active administrator or locking out their own final admin account.

Seed demo accounts only in an explicitly selected demo/development mode, never silently in production:

- `admin@creditrack.com` / `admin123`
- `officer@creditrack.com` / `officer123`
- `viewer@creditrack.com` / `viewer123`

Force seeded users to change their passwords on first login. Never reset their passwords during plugin activation or upgrades.

Provide a profile page where every authenticated user can update their own first name, last name, and email, and change their password by supplying their current password. Users cannot change their own role or active state.

## 3. Data model

Create versioned migrations and properly indexed custom tables for at least the following entities. Include primary keys, foreign keys where the hosting database supports them, unique constraints, created/updated timestamps, and useful indexes.

### Clients

- ID
- first name and last name
- optional email
- phone
- address
- optional date of birth
- optional unique national ID
- occupation
- monthly income
- next-of-kin name, phone, and address
- active/inactive state
- notes
- created and updated timestamps

### Client documents

- ID and client ID
- generated stored filename and original filename
- attachment/media ID or protected storage path
- file size and MIME type
- document type: National ID, Proof of Address, Proof of Income, Collateral Document, Other
- uploader and upload timestamp

### Loans

- ID, client ID, and unique human-readable loan number
- principal amount
- interest rate and interest type: Flat, Monthly, Quarterly, Annual
- term in months
- application/creation date
- approval user and timestamp
- disbursement date and maturity date
- status: Pending, Approved, Disbursed/Active, Completed, Defaulted, Written Off
- purpose, collateral, guarantor, guarantor phone, and notes
- creator/loan officer
- calculated total interest, total repayable, installment/monthly payment, total paid, and outstanding balance
- overdue state and days past due, preferably derived from schedules rather than trusted as independent mutable values
- created and updated timestamps

### Repayment schedules

- ID, loan ID, and unique installment number per loan
- due date
- principal portion, interest portion, and total installment
- paid amount and remaining amount
- status: Pending, Partial, Paid, Overdue
- paid timestamp, overdue flag, and days past due
- created and updated timestamps

### Payments and allocations

- payment ID and loan ID
- amount and payment date
- payment method: Cash, Bank Transfer, Mobile Money, Check, Other
- optional reference number and notes
- advance-payment indicator
- recorder/user and timestamps
- separate allocation rows linking one payment to one or more schedule installments, because a payment may cover multiple installments or only part of one

### Settings

- company name, address, phone, email, logo, currency code/symbol, timezone, and date format
- default interest rate, default term, and default interest type
- overdue grace period in days
- notification enable/disable and reminder window (default next seven days)
- backup retention period
- approval/disbursement permission policy
- updated user and timestamp

### Audit logs

- actor user, entity type, entity ID, action, old values, new values, safe description, IP address, user agent, and timestamp
- audit records must be append-only through the application

### Backups/jobs

- file or job identity, type, status, path/reference, file size, creator, timestamps, completion timestamp, and safe error message

## 4. Client management

Implement a client list with real pagination, search, filtering by active state, sorting, summary statistics, and useful empty/loading/error states. Search first name, last name, phone, email, and national ID.

Administrators and Loan Officers can:

- create clients with field-level validation and clear, field-specific errors;
- edit all permitted client details;
- deactivate a client through a confirmation workflow;
- upload, list, securely view/download, and delete supported documents.

Viewers can only view. Do not permanently delete clients through ordinary UI. Client deactivation must be blocked if business rules require resolution of pending, approved, active, defaulted, or otherwise open loans. Explain the exact blocking loans to the user. Preserve historical clients referenced by financial records.

Create a client details page with profile information, operational statistics, loan history, payment history, and documents. Client-specific loan and payment tabs must query real linked data rather than filter an already incomplete browser-side list.

Validate file extensions, MIME signatures, file size, and authorization. Prevent script execution and direct public enumeration of sensitive identity documents. Serve protected documents through an authorized download controller or equivalently secure mechanism.

## 5. Loan creation and lifecycle

Implement a loan list with search by loan number/client, status filters, overdue filter, pagination, sorting, KPI counts, and links to details. Viewer access is read-only.

The create-loan form must select an existing active client and collect principal, interest rate/type, term, purpose, collateral, guarantor details, and notes. Apply settings defaults. Show a live calculation preview, but always repeat and enforce calculations on the server.

Return actionable, field-specific errors—for example “Selected client no longer exists or is inactive”—instead of generic database-integrity errors. Every submission and long operation must show a loading state, disable duplicate submission, and be idempotent so refreshes/retries cannot create duplicate loans or payments.

Implement and enforce this lifecycle:

1. Create as Pending.
2. Approve with authorized user and approval timestamp.
3. Disburse only an approved loan. Capture disbursement date, compute maturity, mark active/disbursed, and atomically create the complete repayment schedule.
4. Record payments only against eligible disbursed/active loans.
5. Complete a loan only when the full **total repayable** has been paid within the currency rounding tolerance.
6. Support authorized transitions to Defaulted and Written Off with mandatory reason and audit log.
7. Reject invalid status transitions server-side.

The loan detail page must show loan number, borrower and contact details, status, principal, interest, total repayable, paid, outstanding, repayment progress, interest method/rate, term, installment amount, disbursement/maturity dates, purpose, collateral, guarantor information, notes, repayment schedule, and repayment history. Admin/Loan Officer action buttons must reflect valid next actions; Viewer sees none.

## 6. Financial calculation rules and invariants

Centralize calculations in a tested domain service. Document exact formulas, rounding precision, and date behavior. At minimum preserve these invariants:

- `total_repayable = principal + total_interest`.
- `outstanding = max(total_repayable - sum(valid payments), 0)`.
- Payment progress is `total_paid / total_repayable × 100`, capped at 100%.
- A loan is Completed only when outstanding reaches zero, not when payments merely equal principal.
- Example regression rule: principal USD 100 with flat interest of 15% has total repayable USD 115. After a USD 100 payment, paid is USD 100, outstanding is USD 15, progress is approximately 86.96%, and the loan remains Active—not Completed.
- Reject zero/negative payments and, unless an explicit overpayment policy is enabled, reject payment amounts above outstanding.
- Use fixed-point money, consistent currency precision, and deterministic allocation.
- Allocate repayments oldest due schedule first: overdue/partial installments, then pending installments. A single payment may generate multiple allocation rows.
- Partial payment makes an installment Partial; full allocation makes it Paid and records paid time.
- Advance payments must reduce future scheduled balances without losing the immutable original payment record.
- Perform payment insert, allocation updates, loan totals/status update, and audit log in one database transaction.

Define and test the formulas for Flat, Monthly, Quarterly, and Annual interest. If the original product meaning is ambiguous, expose the formula in documentation and settings rather than inventing hidden behavior. Generate schedule portions so rounded installments add exactly to the total repayable; put any rounding remainder in the final installment.

Use calendar-aware month addition with a documented month-end rule. Calculate arrears from unpaid schedule amounts after the configured grace period. `days past due` must be based on the oldest unpaid due installment. A future maturity date alone must not hide a missed installment.

## 7. Payments page

Build a professional payment ledger with real server-side pagination, search, date range, method, loan, client, and overdue filters. Display receipt/payment ID, date, borrower, loan number, method, reference, amount, recorder, and relevant allocation/status information.

Administrators and Loan Officers can record a payment from the payments page or loan detail page. The form must show borrower, loan, total repayable, already paid, current outstanding, next unpaid installment, amount due, and resulting balance. Provide specific validation, progress/loading state, success feedback, and protection against double submission. Viewer is read-only.

Create printable payment receipts containing company identity, receipt number, borrower, loan, date, amount, method/reference, previous and new balance, and receiving user. Do not allow ordinary users to edit or delete posted financial transactions. Corrections must use a controlled reversal/void process with reason, permissions, linked reversing entry, and audit log; never silently overwrite history.

## 8. Dashboard and notifications

Build a fast dashboard using lightweight aggregate queries, with skeletons only during a genuine short request. It must not remain in an endless loading state. Show real database figures:

- active clients;
- total loans;
- gross amount disbursed;
- total collections;
- outstanding portfolio;
- overdue/portfolio-at-risk amount;
- delinquency rate;
- average loan size;
- active, completed, and defaulted loan counts;
- recent loans;
- overdue installments/loans with amount, due date, and days past due;
- installments due within the next seven days with loan number, borrower, amount, and due date.

Honor the notification setting. Add dashboard notification indicators and an operational list for repayments due in the configured window. Implement a daily WordPress cron job to refresh overdue states and notifications. Because WP-Cron depends on traffic, document how production should configure a real system cron to call WordPress cron. Prevent duplicate notifications and mark read/dismissed notifications per user if an inbox is implemented.

## 9. Reports

Reports are available to Administrators and Loan Officers, not Viewers unless a separate capability is granted. Load a lightweight overview immediately and run expensive detailed queries only after the user selects parameters and clicks **Generate report**. Show generation progress, prevent duplicate jobs, and use background processing for genuinely large exports.

Implement at least:

1. Portfolio overview: gross disbursed, outstanding, collections, portfolio at risk, delinquency, average loan, counts by status.
2. Loan portfolio/status report: loan count, principal, repaid, outstanding grouped by status.
3. Disbursement report by date range, officer, status, and borrower.
4. Collections report by date range, payment method, officer, and borrower, including collection efficiency.
5. Arrears/aging report: loan, borrower/contact, unpaid amount, oldest due date, days past due, and aging buckets (1–30, 31–60, 61–90, 91+ days).
6. Portfolio-at-risk reports: PAR30, PAR60, and PAR90 with documented denominator.
7. Repayment schedule/due report for a selected future period.
8. Client statement: loan transactions, repayments, allocations, and running balance.
9. Top borrowers/exposure report: loan count, total borrowed, and outstanding.
10. Loan officer performance: originations, active portfolio, collections, and arrears attributable to officer.
11. Maturity report: loans maturing in a selected period and matured-but-unsettled loans.
12. Written-off/default report with reason, amount, dates, and recoveries if supported.
13. Audit activity report for authorized administrators.

Provide date presets and custom date ranges. All totals must reconcile with ledger data and use the same shared reporting definitions as dashboard KPIs. Display “as of” timestamps and active filters. Provide sortable/paginated tables, appropriate charts, print-friendly layouts, and CSV export. Provide PDF export if it can be generated securely and reliably server-side. Prevent CSV formula injection. Large exports must stream or use queued generation rather than exhausting PHP memory or blocking normal navigation.

## 10. Settings, backup, and operations

Create an administrator-only comprehensive settings page for:

- company identity and report/receipt branding;
- currency, timezone, and formatting;
- lending defaults and interest method;
- grace period and arrears behavior;
- due-reminder window and notification enablement;
- role policy for approvals/disbursements;
- file constraints;
- backup schedule and retention.

Validate settings server-side and audit changes. Dangerous changes must show impact warnings and must not silently recalculate historical loans.

Implement application-data export/backup suitable for WordPress hosting. A backup must include CrediTrack tables, settings, protected client documents, and manifest/checksums. Allow an admin to create and download a backup and list prior backup jobs. Automated daily backups must honor retention. Restoration is destructive: require re-authentication, explicit confirmation, capability and nonce checks, maintenance mode, integrity/schema/version validation, a pre-restore backup, transactional restoration where feasible, and a clear completion/relogin flow. Do not expose backup paths publicly. Document that host-level database and uploads backups remain the preferred production disaster-recovery layer.

Plugin deactivation must preserve all data. Uninstall must preserve data by default and only delete it after a separate explicit administrator opt-in and confirmation.

## 11. Auditability and data integrity

Audit at least login events, user create/update/activation/password reset, client create/update/deactivate, document upload/delete/download where appropriate, loan create/approve/disburse/status changes, payment post/reversal, settings changes, report exports, and backup/restore actions.

Audit entries must include actor, action, affected record, safe before/after values, timestamp, IP, and user agent. Redact passwords, session tokens, document contents, and other secrets. Financial records and audit logs must not have ordinary delete/edit endpoints.

Enforce referential integrity in application services even if the host database does not enforce foreign keys. Use unique indexes for national IDs where present, loan numbers, idempotency keys, installment number per loan, and other business identifiers. Never surface raw SQL errors to end users; log diagnostic detail securely and return a safe, specific application error.

## 12. User experience and visual design

Reproduce CrediTrack as a professional credit-management portal, not a typical blog theme. Use a left navigation/sidebar and top header with company branding, notification indicator, and user/profile menu. Primary navigation:

- Dashboard
- Clients
- Loans
- Payments
- Reports
- Users (Admin only)
- Settings (Admin only)
- Profile

Use a clean blue/neutral design system, cards, KPI tiles, responsive tables, status badges, accessible modal/dialog patterns, clear breadcrumbs, and consistent empty/loading/success/error states. The portal must work on desktop, tablet, and mobile. Meet WCAG 2.1 AA basics: keyboard operation, visible focus, semantic labels, contrast, accessible errors, and reduced-motion consideration.

Every mutation must provide immediate feedback. Long actions display an explicit spinner/progress indicator and disabled state. Validation errors appear beside the exact field and also in an accessible summary. Preserve safe user input after validation failure. Confirm destructive or irreversible actions.

Do not show the WordPress admin bar or standard dashboard to normal CrediTrack users unless their role also has appropriate WordPress administration capability. Prevent borrowers or the public from accessing internal records. The public-facing site may be a minimal branded login/landing page; the management portal requires authentication.

## 13. WordPress security requirements

- Escape all output late and sanitize/validate all input according to context.
- Use `$wpdb->prepare()` for all dynamic SQL.
- Require capabilities plus nonces for state changes; require capability checks for every read of sensitive data too.
- Restrict REST routes with real `permission_callback` functions.
- Use WordPress password APIs and never store plaintext credentials.
- Apply rate limiting to authentication and sensitive exports.
- Prevent IDOR by checking access to every client, loan, payment, document, report, and backup resource.
- Apply upload allowlists, MIME/content verification, randomized names, size limits, protected storage, and authorized streaming downloads.
- Add CSRF, XSS, SQL injection, mass assignment, path traversal, file upload, CSV injection, and privilege-escalation tests.
- Do not put secrets or sensitive financial/identity data in browser local storage, URLs, JavaScript bundles, debug logs, or public uploads.
- Disable verbose production error display and use structured, redacted logging.
- Provide privacy export/erasure integration where legally appropriate, while retaining financial records where legal retention overrides erasure; document this policy boundary.

## 14. Performance requirements

- Use server-side pagination and filtering; do not load entire ledgers into the browser.
- Add indexes based on actual list/report queries: client name/phone/national ID, loan number/status/client/officer/dates, schedules by due date/status/loan, payments by loan/date/method, and audits by actor/entity/date.
- Avoid N+1 queries.
- Keep dashboard queries lightweight and cache safe aggregate results briefly with correct invalidation after financial mutations.
- Generate detailed reports on demand.
- Stream large CSV files and process heavy reports/backups in background jobs with visible status.
- Version/minify assets, load them only on portal pages, and avoid unnecessary third-party libraries.

## 15. Testing and quality gates

Create automated tests using WordPress's PHP testing stack plus suitable JavaScript/end-to-end tooling. Include factories/fixtures and test at least:

- role/capability matrix for every endpoint and page;
- Viewer cannot mutate clients, loans, payments, users, documents, settings, reports, or backups;
- inactive user login rejection;
- client validation and national-ID uniqueness;
- client deactivation blocked by open loans;
- document authorization and malicious upload rejection;
- every interest formula and rounding behavior;
- valid/invalid loan state transitions;
- schedule generation and final-installment rounding reconciliation;
- partial, exact, multi-installment, advance, duplicate, concurrent, excessive, negative, and reversed payments;
- the USD 100 principal / USD 115 total / USD 100 paid regression case remains Active with USD 15 outstanding;
- dashboard totals reconcile to source transactions;
- grace-period arrears and due-next-seven-days notifications;
- report totals reconcile with loan/payment ledgers and filters;
- audit log creation and secret redaction;
- backup manifest/integrity validation and safe restore failure;
- nonce, capability, IDOR, XSS, SQL injection, CSRF, and CSV injection protections;
- plugin activation, migration from an older schema version, deactivation, reactivation, and uninstall-data retention.

Quality gates must include PHP linting, WordPress Coding Standards, static analysis, unit/integration tests, end-to-end tests for critical workflows, JavaScript lint/build if applicable, and a clean install test.

## 16. Required deliverables

Deliver:

1. Complete source for `creditrack-core` and `creditrack-portal`.
2. Installable ZIP files for both packages.
3. Versioned schema/migration system with rollback or safe recovery strategy.
4. Capability matrix and data dictionary.
5. Financial formula and status-transition documentation.
6. REST/API documentation if an API is used.
7. Administrator and Loan Officer user guides.
8. Hosting requirements and production deployment guide covering HTTPS, PHP/MySQL versions, cron, private file storage, backups, SMTP if notifications expand to email, cache exclusions, security hardening, and update procedure.
9. Demo-data command/tool that only runs deliberately and marks demo users for password change.
10. Automated test suite and commands.
11. Seed/sample dataset with clients, pending/approved/active/completed/overdue loans, schedules, partial/full repayments, and reports, without real personal data.
12. Release checklist and changelog.

## 17. Implementation approach for the development session

Before writing code:

1. Inspect the existing repository if it is supplied.
2. Produce a concise gap/assumption register, especially for interest formulas, overpayments, approval separation, payment reversal, data retention, and whether this is single-branch or multi-branch.
3. Propose the plugin/theme directory structure, database schema, capability matrix, loan state machine, payment-allocation algorithm, and migration strategy.
4. Break implementation into independently testable milestones.

Then implement the system milestone by milestone. After every milestone, run relevant automated checks and report concrete results. Do not declare a feature complete until its UI, database operation, authorization, validation, audit logging, loading/error behavior, and tests work end to end.

Recommended milestone order:

1. Plugin scaffold, activation/migrations, roles/capabilities, authentication restrictions.
2. Portal theme shell, navigation, protected routes/pages, profile.
3. Clients and protected documents.
4. Loan calculation engine, lifecycle, schedules, and invariant tests.
5. Payment ledger, allocations, receipts, and reversals.
6. Dashboard, arrears jobs, and due notifications.
7. Reports and exports.
8. Users, settings, audit viewer, backups/restoration.
9. Security/performance hardening, accessibility, end-to-end tests, documentation, and installable ZIPs.

## 18. Definition of done

The rebuild is done only when a fresh WordPress installation can install both ZIPs and an administrator can configure the company, create users and a client, upload protected documents, create and approve a loan, disburse it to generate schedules, post partial and final repayments with correct allocations and outstanding balance, see accurate dashboard notifications/KPIs, generate and export reconciled reports, print a receipt/statement, inspect audit events, create a valid backup, and restore it safely. A Loan Officer can perform only authorized operational work, and a Viewer can inspect permitted information but cannot mutate any record even by calling endpoints directly.

Do not claim encryption at rest unless actual database/file encryption and key management are implemented and documented. Do not claim regulatory compliance merely because security controls exist; identify applicable jurisdictional requirements separately before production launch.

# Prompt ends here

---

## Notes for the next session

Before development begins, decide these product-policy items with the client:

- exact interpretation/formula for Monthly, Quarterly, and Annual interest;
- whether Loan Officers may approve/disburse loans they created or whether maker-checker separation is required;
- whether overpayments are rejected, held as client credit, or refunded;
- permitted payment reversal roles and approval process;
- base currency and whether multi-currency is needed;
- branch support and whether data access must be restricted by branch/officer;
- required legal retention period and local lending/privacy regulations;
- whether reminders remain dashboard-only or expand to email/SMS/WhatsApp;
- expected record volume and hosting environment.

These decisions change financial behavior or authorization and should not be silently assumed during implementation.
