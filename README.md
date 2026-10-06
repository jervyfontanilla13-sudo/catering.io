# 3YOS Catering Management System

3YOS is a Laravel web application for publishing catering services and packages, receiving event reservation requests and inquiries, and managing catering operations. It includes a public guest website and an authenticated administration area.

This repository is the capstone project's source code. A working deployment also requires environment-specific configuration, an initialized database, administrator-created catalog content, and valid third-party credentials where those integrations are enabled.

## Contents

- [Project capabilities](#project-capabilities)
- [Technology](#technology)
- [Reservation and inquiry workflows](#reservation-and-inquiry-workflows)
- [Administrator access](#administrator-access)
- [Payments, refunds, and audit history](#payments-refunds-and-audit-history)
- [Files and backups](#files-and-backups)
- [Requirements](#requirements)
- [Local installation](#local-installation)
- [Environment configuration](#environment-configuration)
- [Testing and frontend assets](#testing-and-frontend-assets)
- [Production deployment](#production-deployment)
- [Turnover checklist](#turnover-checklist)
- [Repository structure](#repository-structure)

## Project capabilities

### Guest website

- Home, about, services, packages, gallery, contact, support, and inquiry pages.
- Service and package listings read from the application's database. Guests can open package details and choose a package when submitting a reservation.
- Reservation form with event details, package selection, date availability, and a reservation code for checking request status.
- Inquiry form for general questions and custom event requirements.
- Confirmation, status update, and inquiry reply emails when mail delivery is configured.

### Administration

- Dashboard, reservation and inquiry management, and support documentation.
- Create reservations on behalf of clients; review submitted event details and use the dedicated acceptance or cancellation actions.
- Manage catalog packages and services, including package descriptions and pricing, service availability, and gallery images.
- Review analytics and generate daily, weekly, monthly, and yearly reports. Report exports are available as CSV and Excel workbooks.
- Manage administrator accounts, review activity logs, and create, upload, download, restore, or delete database backups.
- Record reservation payments and refunds, attach official receipt images, and review reservation financial history.

Catalog records and the initial Primary Admin are not automatically created by the database seeder. After installation, create the administrator through the protected first-run setup and populate the catalog through the admin area.

## Technology

| Area | Implementation |
| --- | --- |
| Server application | PHP 8.2 or later; Laravel 12 (`composer.json` allows `^12.0`; the lock file currently resolves Laravel 12.64.0) |
| Templates and public/admin interface | Laravel Blade, Bootstrap 5.3.3 from the CDN, and project-specific CSS and JavaScript |
| Frontend tooling | Vite 7, Tailwind CSS 4, and the Laravel Vite plugin; the Vite entry points are `resources/css/app.css` and `resources/js/app.js` |
| Database | Eloquent ORM; SQLite is the default. Laravel connection settings are also present for MySQL, MariaDB, PostgreSQL, and SQL Server; install the matching PHP PDO driver and verify database-specific migrations before choosing a non-SQLite production database. |
| Authentication | Database-backed administrator accounts with Laravel sessions and application middleware |
| Integrations | Google reCAPTCHA verification for public reservation and inquiry submissions; SMTP or another configured Laravel mail transport for email |
| Excel reports | OpenSpout |

The public and admin layouts load Bootstrap and their custom styles from `public/`. Vite remains configured for the repository's CSS and JavaScript entry points and is used by the Laravel scaffold page. Build frontend assets with the commands below when preparing the project.

## Reservation and inquiry workflows

### Reservation lifecycle

1. A guest chooses a package and submits their contact information, event type, date, time, venue, guest count, and any additional details.
2. The application validates the request, verifies reCAPTCHA, and creates a reservation with `pending` status and a generated reservation code.
3. The guest can use the reservation code on the reservation status page to view the request's progress.
4. An administrator reviews the request and accepts or cancels it through the dedicated workflow actions. Statuses cannot be edited directly; confirmed reservations are automatically completed at 11:59 PM on the event date. Stored statuses are `pending`, `confirmed`, `completed`, and `cancelled`; the guest-facing progress view labels a pending request as “Under Review” and a confirmed request as “Accepted.”
5. Configured email delivery sends reservation confirmation and applicable status notifications.

Reservation dates must be at least two days in advance. The maximum is 4 active reservations/events per date: both `pending` and `confirmed` reservations consume capacity, while `cancelled` and terminal `completed` reservations do not. Guest submissions, administrator-created reservations, acceptance/cancellation actions, and confirmed-reservation date edits use the same capacity rule. These are application rules, not a statement of general business availability.

### Inquiries

Guests submit contact details, a subject, category, and message. Administrators can review inquiries, track read state, and reply by email. Public reservation and inquiry submissions are rate-limited and require server-verified reCAPTCHA.

## Administrator access

The application has two stored administrator roles:

- **Primary Admin (`full`)**: can access the full administration area, including catalog and gallery management, administrator accounts, analytics, reports, activity logs, and backups.
- **Team Admin (`limited`)**: can access the operational reservation and inquiry areas and support. Primary-Admin-only modules are restricted by server-side middleware.

The first Primary Admin is created through `/admin/setup`, not a default username/password or database seeder. The setup page is available only when no Primary Admin has been created and a valid `PRIMARY_ADMIN_SETUP_KEY` is configured. All administrator passwords must be at least 12 characters and include uppercase and lowercase letters, a number, and a symbol; password confirmation is required. There is no shared demo administrator credential.

Administrator passwords can be reset through the configured password-reset mail flow. Disabling an administrator or resetting their password invalidates their existing sessions. Creating an administrator requires the active Primary Admin to confirm their own password in the same create request; it is checked against that account's database hash and is not stored as a reusable session authorization. Five incorrect confirmations per Primary Admin and source IP are allowed in a five-minute window. Keep the setup key private and remove it from the environment after first-run setup. This step-up check does not apply to the initial `/admin/setup` flow, where no Primary Admin account exists yet.

## Payments, refunds, and audit history

- Payments are recorded against a reservation with a date, type, amount, method, optional notes, and optional receipt image.
- Supported payment methods are Cash, GCash, Bank Transfer, and Other. The reservation financial service derives gross payments, completed refunds, net paid, remaining balance, and payment status from the transaction history (while retaining compatibility with legacy payment totals).
- Refunds are recorded with a date, amount, method, optional reason, and a completed status. The application prevents a refund from exceeding net payments received.
- Payment records can be edited or deleted only after current administrator password confirmation. The application records payment changes, deletions and reasons, refunds, and receipt upload/replacement/removal in reservation activity history.
- Payment receipts and service contract images are stored on the private local disk and served through authenticated routes. Gallery images are intentionally stored on the public disk for the public gallery.

The application does not currently provide a refund edit/delete workflow. Payment and refund totals are application records and should be reconciled with the business's accounting records.

## Files and backups

The default local disk stores private files under `storage/app/private`; this includes payment receipts, service contracts, and generated backups. The public disk stores gallery images under `storage/app/public` and is exposed through Laravel's `public/storage` symbolic link.

Admin-created backups use the `3YOS_JSON_BACKUP` format, currently format version `2`, and are JSON data encrypted with Laravel's application encryption key (`APP_KEY`) before storage under `storage/app/private/backups`. The format and version metadata stay inside the encrypted payload. A small encrypted private metadata cache lets the Backup page display the format, original creation time, and most recent validation without decrypting every full backup on each page view. If older files have no cache, their details remain unknown until an administrator explicitly validates them. Validation authenticates/decrypts the archive, checks its format, supported tables, row structure, and known reservation/payment/refund relationships, and does not change database content. Version 2 is current; version 1 and recognized versionless legacy backups have an explicit compatibility path, but their archived `users` rows are never restored. Unsupported formats and versions are rejected before database changes. Uploading a compatible older backup stores it encrypted in the current version after removing archived user records. Restore requires an authorized Primary Admin, password confirmation, successful validation, and a safety backup; it replaces supported business records, merges historical activity entries without deleting current audit history, preserves administrator accounts and sessions, and verifies table counts and supported relationships after restoration. Imported activity rows are detached from current user IDs and retain actor snapshots where available. There is no public full-system restore operation. Uploaded legacy JSON backups may be unencrypted and require explicit confirmation before restore. The backup service includes only the application tables it explicitly supports; it is not a full server, uploaded-file, or source-code backup.

The generic `settings` table has no application-defined keys or runtime read/write call sites. Because it can hold arbitrary values, backups store it empty and restore leaves the live table unchanged; existing settings values are not copied into backup archives.

Older plaintext `.json` backup files, if present in `storage/app/backups`, are legacy archives; new backup creation always writes encrypted `.json.enc` files under the private backup directory. They are not exposed through Laravel's public storage link, but a full administrator may download them through the authenticated backup controller, so do not leave obsolete plaintext copies indefinitely. To retire a legacy file without risking data loss, first upload it through the authenticated Backup page; compatible uploads are validated and stored encrypted as a current-format `.json.enc` backup while preserving the archive's original `created_at`. Verify the encrypted replacement's format, compatibility, and creation date in the backup list, and test its restore only in a separate test database. Keep the original until verification and retention approval are complete, then remove it through an authorized, controlled server-side retention process; never expose it through `public/` or delete it before the encrypted copy is verified.

If no administrator can sign in, authorized server access can use the interactive `php artisan admin:reset-for-turnover` command to remove administrator accounts while preserving business data and historical activity; the first Primary Admin must then be created through the protected setup flow. Review the command's confirmation and configure a valid one-time setup key before running it. This recovery command was not run during development restore testing.

The backup format version describes the backup JSON structure, not the Laravel or application release. Increase it only when a structural restore change makes an existing format incompatible or requires migration, such as changing required fields or relationships; do not change it for routine application or UI updates.

`APP_KEY` is required by Laravel's encryption and protects encrypted backups. Supply it through the production secret/environment manager; never hardcode it in source, commit it to GitHub, display it, or include it in a backup. Keep a secure recovery copy of the production key separately from encrypted backup files: losing the key may prevent decryption of those backups. Keep off-server copies of backups and uploaded private files as part of the deployment's backup policy. A backup stored on the same host is not protection against host or disk loss.

### Backup and database recovery

1. Open **Admin → Backups**. Check the database status, then create or upload a backup. New backups remain encrypted `.json.enc` files in private storage; the application does not create plaintext production backups.
2. Use **Validate** before restoration. The validation action checks encryption/integrity, JSON structure, supported version, tables, and supported relationships. A failed validation does not modify the database.
3. **Restore** requests the current administrator password and warns that business data will be replaced. The application validates again, creates an encrypted pre-restore safety backup, restores supported data, preserves current administrator accounts and historical audit records, then checks restored table counts and key relationships. Keep the safety backup; it is not automatically removed.
4. Review the recovery result and activity log. A successful message is shown only after post-restore verification passes. If verification reports warnings or failure, do not treat the restored application as ready for normal use; investigate the database and retain both backups.

The page performs its database health check only when opened or when **Check Database** is explicitly selected. It checks required application tables and backing tables for configured database-driven sessions, cache, and queues; it does not expose connection credentials or repeatedly poll the database. If a missing session table prevents Laravel from serving the request at all, use authorized server/hosting recovery instead of expecting the page to load. The metadata cache is stored under `storage/app/private/backups/.metadata`; it is display-only, and every explicit validate/restore operation checks the actual backup file again.

### Administrator access recovery

The Emergency Super Admin uses `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` from the deployment environment and signs in through the normal `/admin/login` form. It is independent of the `users` table, receives the existing full-admin route authorization, and is represented in audit logs by its environment-configured email without storing its password. It is not a database-failure bypass: Laravel sessions, throttling, activity logs, and application data still require the database and configured backing services to be available. Keep the beneficiary-owned Primary Admin setup at `/admin/setup`; do not replace it or create a database record for the Emergency Super Admin.

When the database is healthy but normal administrator access needs to be recovered, the Emergency Super Admin can access the existing full-admin recovery areas. If a new beneficiary-owned Primary Admin must be created, use the existing protected `/admin/setup` flow. Authorized server access can also use the interactive `php artisan admin:reset-for-turnover` command to remove inaccessible administrator accounts while preserving business data and activity history, then complete the protected setup flow. Keep the setup key private and remove it after use.

### Complete database failure

The Laravel admin page cannot restore a database it depends on for application data and potentially sessions, cache, queues, and authentication. For an unavailable or lost database, use authorized hosting/server access:

1. Recreate the database and restore the correct deployment configuration and protected environment values, including the original `APP_KEY` needed to decrypt backups.
2. Run the application's Laravel migrations against the recreated database.
3. Start the application with its normal database connection available, sign in as an authorized Primary Admin, and restore the encrypted backup from **Admin → Backups**. If the web application cannot be made available first, use a controlled operator-run recovery procedure against that database; do not expose an unauthenticated recovery endpoint.
4. Confirm the recovery verification result, administrator access, reservations, payments/refunds, inquiries, packages/services, activity history, and access to separately backed-up private files and public gallery files.

This database backup does not contain deployment configuration, application code, or uploaded files such as receipts, contracts, and gallery images. Keep separately protected off-host copies of those assets and the original application encryption key. Run migrations before restoring so the tables expected by the application exist.

## Requirements

- PHP 8.2 or later with the extensions required by Laravel and the selected database driver.
- Composer 2.
- Node.js and npm for installing and building the frontend dependencies.
- SQLite for the default local setup, or a configured database server and matching PHP driver.
- A writable `storage/` directory and `bootstrap/cache/`.
- Google reCAPTCHA site and secret keys for guest reservation and inquiry submissions.
- A configured mail transport if the deployment must deliver confirmation, status-update, password-reset, or inquiry-reply emails.

## Local installation

Run commands from the repository root. These instructions create `.env` only if it is absent; do not overwrite an existing environment file or development database.

### Windows PowerShell

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

### macOS/Linux

```sh
composer install
test -f .env || cp .env.example .env
```

Then, on either platform:

1. Edit `.env` with the local application URL, database connection, mail settings, reCAPTCHA keys, and a private setup key. For SQLite, ensure the configured database file exists; do not point a new installation at an existing database unless that is intended.
2. Generate an application key for a new installation:

   ```sh
   php artisan key:generate
   ```

   Do not run this against an existing installation: changing `APP_KEY` makes existing encrypted backups undecryptable and can invalidate encrypted application data such as sessions.
3. Apply pending schema migrations:

   ```sh
   php artisan migrate
   ```

   Review and back up an existing database and its files before deployment migrations. Do not use `migrate:fresh` or `db:wipe` on a database containing data.
4. Install and build frontend dependencies:

   ```sh
   npm ci
   npm run build
   ```
5. Create the public storage link for gallery images:

   ```sh
   php artisan storage:link
   ```

6. Start the local web server:

   ```sh
   php artisan serve
   ```

   Open the URL reported by Artisan. Run `npm run dev` in a second terminal if you are developing Vite-managed assets.
7. Open `/admin/setup` and create the first Primary Admin using the configured setup key. After successful setup, remove `PRIMARY_ADMIN_SETUP_KEY` from the environment and run `php artisan config:clear` locally. In production, rebuild the cache after all environment values are final with `php artisan optimize`.
8. Sign in and enter the services, packages, and gallery content needed by the public site. Confirm reCAPTCHA and email delivery before accepting live submissions.

## Environment configuration

Create a deployment-specific `.env` from `.env.example`; the `.env` file is not the configuration template and must not be committed or included in a release package. `.env.example` is the tracked template. Never put real credentials in documentation, source files, screenshots, or issue reports.

| Variable | Purpose |
| --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL` | Application identity, deployment mode, debug behavior, and canonical URL. Use `APP_ENV=production`, `APP_DEBUG=false`, and the real HTTPS URL in production. |
| `APP_KEY` | Laravel encryption key. Generate once per installation and store securely outside source control. Do not replace it casually; encrypted backups depend on it. |
| `APP_TIMEZONE` | Application timezone for local date/time handling. Defaults to `Asia/Manila`; set it explicitly in each deployment environment. |
| `PRIMARY_ADMIN_SETUP_KEY` | Private first-administrator setup credential. Use at least 32 characters, configure it before initial setup, then remove it and refresh cached configuration. |
| `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` | Optional environment-controlled Emergency Super Admin credentials for the normal `/admin/login` form. Configure both privately; blank values disable emergency sign-in. The account is not stored in the `users` table and does not work as a bypass for a completely unavailable database. |
| `DB_CONNECTION`, `DB_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` | Database driver and connection settings. `DB_CONNECTION=sqlite` is the default; configure the appropriate values for a server database. |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE` | Session storage/lifetime and cookie transport. Set `SESSION_SECURE_COOKIE=true` when serving production over HTTPS. |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `RESEND_API_KEY` | Outbound email transport and sender identity. For Resend, set `MAIL_MAILER=resend`, provide `RESEND_API_KEY`, and use a sender address accepted by Resend. Use provider-issued credentials, not a personal account password. |
| `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY` | Public reCAPTCHA widget key and server-side verification secret. These are read from `config/services.php`; add them privately to `.env` and do not expose the secret key. |
| `FILESYSTEM_DISK` | Default filesystem disk; the local private disk is the default. Gallery images explicitly use the public disk. |
| `CACHE_STORE`, `QUEUE_CONNECTION` | Laravel cache and queue drivers. Configure their backing stores/tables according to the chosen deployment. Public reservation confirmation and admin notification emails use Laravel's background queue connection so SMTP delays do not hold up reservation submission. |

The example environment file provides safe placeholders, not working production credentials. Validate that the required tables for database-backed sessions, cache, or queues exist before selecting those drivers.

## Testing and frontend assets

The project uses PHPUnit through Laravel's test runner. The test configuration uses an in-memory SQLite database, so feature tests do not need to use the application's local development database.

Run all unit and feature tests:

```sh
php artisan test
```

Or run the Composer test script:

```sh
composer test
```

To run a focused group, for example:

```sh
php artisan test --filter=ReservationPaymentTest
```

Build the production frontend assets with:

```sh
npm ci
npm run build
```

## Production deployment

1. Provision the host, PHP extensions, database, HTTPS endpoint, and a deployment-specific environment. Point the web server document root at Laravel's `public/` directory; do not expose the repository root, `.env`, database file, `storage/`, or backups over HTTP.
2. Create `.env` separately from source control. Set `APP_ENV=production`, `APP_DEBUG=false`, the production `APP_URL`, the chosen database connection, HTTPS session cookies, and private mail, reCAPTCHA, and setup credentials. Use a unique `APP_KEY` for a new installation and preserve it for the life of that installation.
3. Install production dependencies and frontend assets:

   ```sh
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```

4. Back up existing database content and uploaded files before applying migrations. Then run:

   ```sh
   php artisan migrate --force
   php artisan storage:link
   ```

   The current migrations include administrator session-version support and a migration that moves legacy service contract files from public storage to private storage. Ensure private and public storage directories are writable by the application and keep private storage outside the web root.
5. Create the first Primary Admin at `/admin/setup` using the private setup key. Remove the key from the deployment environment; the final `php artisan optimize` step below rebuilds cached configuration without it. Then create any additional administrator accounts and add the live service/package/gallery content.
6. Verify login, role access, reservation submission/status lookup, inquiry handling, email delivery, private contract/receipt access, gallery visibility, report exports, and backup/restore on the actual deployment configuration.
7. Apply Laravel's deployment optimizations only after environment values are finalized:

   ```sh
   php artisan optimize
   ```

   Rebuild the configuration cache whenever deployment environment configuration changes. Keep production logs, backups, database files, and private uploads access-restricted; maintain tested off-host backups.

### Railway deployment

This repository includes a Railway Docker deployment using PHP 8.3 with the ZIP and MySQL PDO extensions, a Vite asset build, and Apache listening on Railway's `PORT`. The container entrypoint runs `php artisan migrate --force` before Apache starts, so migration errors stop the app from becoming healthy. Migrations run after the Railway storage volume is mounted because one migration moves legacy service-contract files between storage disks.

1. Set the Railway service's **Root Directory** to the directory containing this `README.md`, `composer.json`, and `Dockerfile`.
2. Add a Railway MySQL service and connect the web service to its private network. In the web service variables, set `DB_CONNECTION=mysql` and `DB_URL` to a Railway reference to the MySQL service's `MYSQL_URL` (for example, `${{MySQL.MYSQL_URL}}`, substituting the actual service name).
3. Set the production Laravel variables privately in Railway: `APP_ENV=production`, `APP_DEBUG=false`, the deployed HTTPS `APP_URL`, a stable generated `APP_KEY`, `SESSION_SECURE_COOKIE=true`, and any needed SMTP, reCAPTCHA, and first-admin setup credentials. Never commit `.env` or paste credentials into source files.
4. Before the first deployment, add a Railway volume mounted at `/var/www/html/storage` to retain uploads, private files, and local logs. Without persistent storage or an external object store, files written to the container filesystem are ephemeral. If an existing deployment already contains uploads or backups, back them up and migrate them to the persistent volume before switching traffic.
5. Deploy the web service. Railway builds the Docker image, runs migrations at container startup, and starts Apache only after they succeed. Create the first Primary Admin at `/admin/setup`, then remove `PRIMARY_ADMIN_SETUP_KEY`.

## Turnover checklist

- Transfer control of hosting, DNS, database, SMTP, reCAPTCHA, and source-control accounts to the designated system owner; remove departing operators' access.
- Provide `.env` values through an approved secret-management channel, never by committing `.env` or sending secrets in the repository.
- Confirm the designated Primary Admin can sign in, has a recovery email route, and can manage appropriate Team Admin accounts.
- Remove `PRIMARY_ADMIN_SETUP_KEY` after initial setup and confirm `/admin/setup` is no longer available for account creation.
- Preserve `APP_KEY` securely with the backup recovery instructions. Test an encrypted backup restore before handoff and retain a separate off-host copy.
- Verify the recipient can access private contract and receipt files, public gallery images, reports, and the application's logs.
- Document the deployed URL, chosen database driver, migration/release process, backup frequency and retention, recovery owner, and any outstanding operational limitations.

## Repository structure

```text
3yos-final-ver/
├── app/
│   ├── Http/Controllers/     # Guest, admin, authentication, payment, and report workflows
│   ├── Http/Middleware/      # Admin access, role checks, password confirmation, activity capture
│   ├── Http/Requests/        # Request validation
│   ├── Models/               # Users, reservations, payments, packages, services, and related data
│   ├── Services/             # Financial calculations, reports, backups, and supporting logic
│   └── Support/              # Shared application rules
├── bootstrap/                # Laravel bootstrap and middleware aliases
├── config/                   # Application, database, mail, filesystem, session, and service config
├── database/
│   ├── migrations/           # Versioned schema and file-storage migrations
│   └── seeders/              # DatabaseSeeder; no default administrator or catalog is seeded
├── public/
│   ├── css/ and js/          # Public/admin styles and browser scripts
│   └── images/               # Application branding and static images
├── resources/
│   ├── css/ and js/          # Vite entry points
│   └── views/                # Blade templates for guest pages and admin modules
├── routes/
│   ├── web.php               # Public, authentication, and administrator routes
│   └── console.php           # Artisan console commands
├── storage/                  # Logs, private uploads/backups, and public-disk files
├── tests/
│   ├── Feature/              # Application workflow and security tests
│   └── Unit/                 # Isolated unit tests
├── .env.example              # Safe environment template; create a separate local/deployment .env
├── composer.json             # PHP dependencies and Artisan/Composer scripts
├── package.json              # Frontend dependencies and Vite scripts
└── README.md
```
