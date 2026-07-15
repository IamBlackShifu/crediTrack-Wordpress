# Release checklist

- Run `composer test:domain`, PHP lint, WordPress Coding Standards, static analysis, integration and critical-path browser tests.
- Test fresh activation and upgrade on supported WordPress, PHP, MySQL and MariaDB versions.
- Verify administrator, officer and viewer capability boundaries by direct forged requests as well as UI.
- Reconcile schedules, payments, dashboard KPIs and reports against fixtures.
- Test backups, restore failure handling, protected documents, cron, HTTPS and cache exclusions in staging.
- Scan both ZIPs for secrets, development data and unintended files; take a production backup; record rollback steps.

Version 0.2.0 implements protected documents, settings/user workflows, compensating reversals, core operational reports/CSV, integrity-checked application backup/restore, notifications, and opt-in demo tooling. Before regulated production use, complete jurisdiction-specific APR/disclosure and early-settlement rules, independent security review, full WordPress integration/concurrency/browser testing, privacy workflow validation, large-volume performance tests, SMTP monitoring, and a host-level disaster-recovery exercise.

Version 0.3.0 repairs the local MariaDB upgrade path, first-login password transition, and traffic-triggered overdue/notification refresh. Run `php tests/live-diagnostic.php /path/to/wordpress` after upgrades to verify schema columns, forced-password flags, schedule arrears, notification counts, and the installed schema version without exposing credentials.

Version 0.3.5-rc2 is the production-staging candidate. Local domain, syntax, asset, metadata, debug/secret-signature, and package-structure checks pass. Promotion to a final production release remains conditional on completing `docs/online-e2e-checklist.md` against the target HTTPS/webserver/database/cache/mail/cron stack and recording a successful rollback and disaster-recovery exercise.
