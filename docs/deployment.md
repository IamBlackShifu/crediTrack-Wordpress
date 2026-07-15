# Deployment and operations

## Supported baseline

Use WordPress 6.6+, PHP 8.1+ with Fileinfo and ZipArchive, and MySQL 8.0+ or MariaDB 10.6+. Use HTTPS, secure cookies, least-privilege database credentials, monitored SMTP, and host-level database plus `wp-content` backups.

Deploy to staging first. Install and activate CrediTrack Core before CrediTrack Portal, then save WordPress permalinks once. Never test with real identity documents until private storage has been verified from an unauthenticated browser.

## WordPress production configuration

Use production-safe values in `wp-config.php` appropriate to the host:

```php
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );
define( 'FORCE_SSL_ADMIN', true );
define( 'DISABLE_WP_CRON', true ); // Only after installing a real scheduler.
define( 'WP_ENVIRONMENT_TYPE', 'production' );
```

Keep diagnostic logging restricted and outside publicly served locations. Do not log passwords, cookies, client identity data, or uploaded-document contents.

The opt-in WP-CLI demo command refuses to run when `WP_ENVIRONMENT_TYPE` is `production`. Use synthetic data only on development or staging systems.

## Protected storage

CrediTrack stores protected documents and application backups beneath `wp-content/creditrack-private`. Apache and IIS denial files are created by the plugin. Nginx must include an explicit rule before PHP/front-controller handling:

```nginx
location ^~ /wp-content/creditrack-private/ {
    deny all;
    return 404;
}
```

Confirm that a guessed file beneath this directory returns 403/404 while an authorized portal download succeeds. Ensure PHP can write to the directory but directory listing and script execution are unavailable.

## Cron, cache, and mail

Configure a real scheduler to request `wp-cron.php` at least every 15 minutes, and monitor failures. Exclude authenticated `/creditrack/`, `wp-admin/admin-post.php`, protected downloads, CSV exports, and backup downloads from page/CDN caching. Preserve WordPress no-cache response headers. Configure SPF, DKIM, DMARC, and monitored SMTP before relying on password-reset email.

## Deployment sequence

1. Back up the database and `wp-content`; record the restore command/process.
2. Enable a staging maintenance window if replacing an existing release.
3. Update CrediTrack Core, then CrediTrack Portal.
4. Open an authenticated portal page to allow migrations and overdue refresh.
5. Run `php tests/live-diagnostic.php /path/to/wordpress` where shell access exists.
6. Execute the online E2E checklist with Administrator, Loan Officer, Viewer, inactive-user, and anonymous sessions.
7. Review PHP/webserver logs for new warnings without exposing them to browsers.
8. Promote only after reconciliation, backup restore, concurrency, mail, cron, cache, and authorization gates pass.

## Rollback

Do not downgrade only one package. If a release must be rolled back, enter maintenance mode, restore both prior package directories and the matching pre-upgrade database/files backup, then rerun smoke tests. Database migrations are forward-managed; copying old PHP over a migrated database is not a guaranteed rollback.

## Compliance boundary

This software does not claim regulatory compliance or encryption at rest. Determine jurisdiction-specific lending, privacy, reporting, early-settlement, disclosure, and retention obligations before production use.
