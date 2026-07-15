# CrediTrack for WordPress

CrediTrack is a single-tenant credit and microfinance operations portal produced by **Infinity Lines of Code (Pvt) Ltd**. The repository contains two matching installable packages:

- `creditrack-core`: domain logic, database tables, security, lending workflows, reports, protected documents, audit records, notifications, and backups.
- `creditrack-portal`: the authenticated responsive management interface and public landing screen.

## Requirements

- WordPress 6.6 or newer
- PHP 8.1 or newer with Fileinfo and ZipArchive
- MySQL 8.0+ or MariaDB 10.6+
- HTTPS and secure cookies
- Working WordPress email delivery for password resets
- A real server scheduler for WordPress cron

## Staging installation

1. Take a host-level database and `wp-content` backup.
2. Upload and activate the matching CrediTrack Core ZIP.
3. Upload and activate the matching CrediTrack Portal ZIP.
4. Save **Settings → Permalinks** once.
5. Sign in as a WordPress administrator and open `/creditrack/settings/`.
6. Configure company identity, currency, timezone, lending defaults, notifications, protected-file limits, and retention.
7. Run [the online E2E checklist](docs/online-e2e-checklist.md) before using live client data.

Install the plugin before the theme. During upgrades, update Core first and Portal second.

## Verification

Local domain tests:

```shell
php tests/domain-test.php
```

Read-only diagnostics against a WordPress installation:

```shell
php tests/live-diagnostic.php /absolute/path/to/wordpress
```

See [deployment and operations](docs/deployment.md), [financial rules](docs/financial-rules.md), [user guide](docs/user-guide.md), and [release checklist](docs/release-checklist.md).

## Production boundary

The `0.3.5-rc2` archives are staging release candidates. Local tests have passed, but production promotion still requires the documented HTTPS, webserver, mail, cron, cache, role-boundary, concurrency, backup/restore, browser, and critical-workflow E2E checks on the target hosting stack.

CrediTrack does not claim regulatory compliance or encryption at rest. Confirm jurisdiction-specific lending disclosures, privacy, retention, settlement, and reporting requirements before live use.

## Data safety

Plugin deactivation and uninstall preserve CrediTrack financial data. Protected files are stored under `wp-content/creditrack-private`; direct web access must be denied at the webserver. Application backups supplement, but do not replace, host-level database and filesystem backups.

Copyright © 2026 Infinity Lines of Code (Pvt) Ltd. All rights reserved.
