# Changelog

## 0.3.5 - 2026-07-16

- Promoted the tested rc4 build to the production release without changing financial or workflow behavior.
- Finalized native wp-admin integration, Loan Officer form routing, portable WordPress packages, responsive portal styling, and compact grouped navigation.
- Completed production metadata, documentation, static checks, domain tests, artifact scans, and package verification.

## 0.3.5-rc4 - 2026-07-16

- Consolidated Users, Audit log, Settings, and Backups into an expandable Administration sidebar group.
- Made the navigation independently scrollable and moved the product credit into normal layout flow to prevent overlap on short screens.
- Reduced sidebar spacing while preserving mobile and keyboard navigation.

## 0.3.5-rc3 - 2026-07-16

- Added native wp-admin CrediTrack overview and settings pages.
- Added a Plugins-screen Settings shortcut and an Appearance > CrediTrack Portal information page.
- Reused the secured settings validation, persistence, and audit workflow for both portal and wp-admin configuration.

## 0.3.5-rc2 - 2026-07-15

- Fixed the Loan Officer WordPress-admin guard intercepting secured `admin-post.php` mutations before CrediTrack handlers ran.
- Aligned loan approval and disbursement buttons with the configured Loan Officer policy.

## 0.3.5-rc1 - 2026-07-15

- Prepared the production-staging release candidate and online E2E runbook.
- Added explicit no-cache headers to authenticated portal responses.
- Replaced development-session documentation with installation, deployment, verification, rollback, and support guidance.
- Completed package secret/debug/development-artifact and archive-structure checks.

## 0.3.4 - 2026-07-15

- Extended the dashboard visual system throughout every CrediTrack portal screen.
- Unified navigation, page headings, action bars, cards, filters, forms, tables, badges, alerts, charts, and responsive layouts.
- Improved mobile action stacking, data-table readability, focus states, and print presentation.

## 0.3.3 - 2026-07-15

- Rebuilt the live dashboard to match the modern theme preview with portfolio KPIs, a six-month trend chart, portfolio health, notifications, recent loans, and repayment alerts.
- Added responsive dashboard layouts for desktop, tablet, and mobile displays.

## 0.3.2 - 2026-07-15

- Made forced first-login password changes atomic and verified before clearing the requirement.
- Re-established the authenticated session after a successful password change.
- Added the WordPress Themes-page preview image.

## 0.3.1 - 2027-07-15

- Standardized package authorship as Infinity Lines of Code (Pvt) Ltd.
- Removed the sidebar CT brand badge.

## 0.3.0 - 2027-07-15

- Corrected the forced first-login password workflow, refreshed authentication cookie, dashboard redirect, reset-link completion, and readable validation feedback.
- Reworked schema upgrades to avoid malformed `dbDelta()` alterations against existing one-line table definitions.
- Added request-throttled overdue recalculation and notification refresh so days past due and header alerts do not depend solely on daily WP-Cron.
- Rebuilt the top bar with notification icon, avatar and role, accessible profile menu, profile/security link, session lock, and sign out.
- Added a reusable read-only live-installation health diagnostic.

## 0.2.2 - 2026-07-15

- Fixed first-login password changes by securely reissuing the authenticated session after WordPress invalidates old cookies.
- Added password confirmation, current-password verification, reuse prevention, forced-change guidance, and reset-link completion handling.
- Completed a responsive UI sweep covering form alignment, checkbox sizing, focus states, mobile navigation, tables, cards, and actions.
- Constrained the public landing experience to the visible viewport with compact layouts for short and small screens.

## 0.2.1 - 2026-07-15

- Added accessible, dependency-free SVG charts to every operational report.
- Made the payment ledger compatible with databases that have not yet added reversal metadata.
- Added an explicit schema-repair migration for payment reversal columns.

## 0.2.0 - 2026-07-15

- Added protected client documents, per-user notifications, user administration, compensating payment reversals, reports and hardened CSV export.
- Added integrity-checked backup/download/restore with re-authentication and pre-restore backup.
- Added declining-balance amortization, explicit rate-basis labels, login throttling, forced demo password changes and opt-in WP-CLI demo data.
- Redesigned the public landing page and portal refinements with Infinity Lines of Code product branding.

## 0.1.1 - 2026-07-15

- Redirect authenticated homepage visitors into the portal and add a clear login call to action for signed-out visitors.
- Replace the settings placeholder with a validated, capability-protected, audited configuration form.
- Apply configured interest type, rate, and term defaults to new-loan forms.

## 0.1.0 - 2026-07-15

- Initial custom-table schema, roles, authentication restrictions, audit trail, client and loan services.
- Deterministic interest calculations, schedule generation, atomic payment allocation, portal dashboard and ledgers.
- Initial domain regression suite and deployment documentation.
