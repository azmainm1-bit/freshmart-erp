# Operating and releasing FreshMart

## Current scope

This repository is a portfolio and local-demo application. The supplied Docker services are development tools, not a production deployment definition. The PHP development server is suitable for a local review, not an internet-facing store.

## Before a real rollout

Use a supported PHP-FPM runtime behind an HTTPS web server. Set its document root to `laravel/public`, give the application write access only where required, and configure the database, application key, session security and trusted proxies for that environment.

Set `APP_ENV=production` and `APP_DEBUG=false`. Use unique secrets rather than the example configuration. Preserve the application key for an existing installation. Configure logging, monitoring, process supervision, the scheduler and any queue workers required by the deployment. Test transactional workflows with the store's tax, rounding, return and till policies before using real records.

The developer helpers bind to loopback deliberately. Exposing the dev server or fixture accounts is not a production setup.

## Initial administrator

After installing dependencies, configuring the production environment and reviewing migrations, seed permissions and create the initial account:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan app:create-admin
```

The administrator command generates a password and displays it once. Store it securely. Never seed the demo dataset into a real store database. The default and development-user seeders reject production environments.

## Release rehearsal

1. Back up the database and verify that the backup can be restored into a separate environment.
2. Run CI and review failures, including dependency audits.
3. Rehearse migrations against a copy of the relevant schema and representative data.
4. Validate checkout, payments, receiving, returns and till close using the roles that will perform them.
5. Run `php artisan erp:reconcile` and investigate every mismatch.
6. Schedule the release with a recovery plan for both application code and database changes.

A successful source build alone does not establish production readiness. Avoid blindly rolling back schema migrations after business records have been written against the new version.

## Backups

Use PostgreSQL-native database backups and restore them regularly into an isolated environment. Retain the application configuration and key through an appropriate secret-management process, separate from the public source repository. Define retention, recovery-time and recovery-point targets with the business.

This package intentionally excludes the database dumps and legacy backup scripts from the uploaded archive. It does not claim that a Laravel production backup/restore procedure has already been exercised.

## External integrations

Payment types are recorded in the application; they do not initiate charges. Payment gateways, fiscal printers, hardware-specific scanner behavior, offline synchronization, banking reconciliation and data imports require their own integrations and acceptance testing.
