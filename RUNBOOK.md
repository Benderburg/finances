# Norocel 2 runbook

## Build and local rehearsal

Follow README.md. Lockfiles are authoritative. Build `frontend/` with Node22.14+, `npm ci`, `npm run build`; deploy resulting `backend/public/build`, `public/sw.js`, manifest and icons together. PHP runtime needs 8.2+ with PDO MySQL, mbstring, intl and BCMath, Composer dependencies, MySQL8.4/InnoDB/utf8mb4. Laravel12 was selected for the available PHP8.2; schedule coordinated PHP/Laravel upgrade before Laravel12 security support ends (2027-02-24).

Local profile `docker compose --profile web up -d --build` checks PHP-FPM/Nginx at loopback8085. It binds the already installed checkout/dependencies and fake localhost DB; APP_ENV=production and APP_DEBUG=false exercise the production request path. Its plain HTTP/Secure=false and fixed MySQL password are local-only. No external host is changed. The supplied nginx.conf is the tested starting point, not a TLS termination configuration.

## Host preparation and release

The actual host/provider/PHP/MySQL/SSH/document-root capabilities have not been supplied. Confirm these before selecting its deployment path. Serve only `backend/public`; neither project root nor `.env`/storage/vendor should be web-accessible. Nginx uses `try_files` to index.php; shared Apache can use `backend/public/.htaccess` with mod_rewrite and an isolated document root. Keep code immutable per release, writable private storage/bootstrap cache, and durable MySQL, sessions and prior restore backups. No production Node process is required.

Prepare an isolated staging release with `composer install --no-dev --prefer-dist --optimize-autoloader`, built frontend, locked code and a separate DB/user. Use a least-privilege runtime DB user; migrations use a separate operator credential. Set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-actual-host
APP_KEY=<existing persistent secret; generate once on new deployment>
DB_CONNECTION=mysql
DB_HOST=<private database host>
DB_PORT=3306
DB_DATABASE=<new reviewed database>
DB_USERNAME=<runtime user>
DB_PASSWORD=<secret outside Git>
SESSION_DRIVER=file
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=your-actual-host
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=smtp
MAIL_HOST=<approved SMTP host>
MAIL_PORT=<provider port>
MAIL_USERNAME=<secret>
MAIL_PASSWORD=<secret>
MAIL_FROM_ADDRESS=<verified sender>
LOG_LEVEL=warning
```

Use provider-required TLS mail scheme and trusted reverse proxy configuration. APP_KEY must stay stable across releases to retain encrypted sessions; rotation invalidates sessions and is a separate procedure. Single origin avoids broad CORS and third-party-cookie assumptions. Persistent shared session/cache storage is needed if the host uses multiple instances (or explicitly configured supported shared drivers). Do not expose PHP-FPM9000/MySQL3306 publicly.

On the staging release, run `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`, then restart PHP-FPM. Build/cache against that release's actual environment. In the local bind-mounted Docker rehearsal do not cache container DB_HOST=mysql into files used by the native host's PHP server. Restart queues if a queued mail configuration replaces sync.

Verify `/up`, deep-link reload `/operations` and `/reports`, hashed assets, valid HTTPS, cookie flags, `/sanctum/csrf-cookie`, session login, 401 after logout, email verification/reset/change-confirmation delivery to a designated test address, app owner access, different-currency totals, valid download headers, restore on a disposable owner, manifest/icons and service worker scope. Block direct `.env` and arbitrary PHP execution. Run the MySQL integration suite against a disposable matching DB. Never point PHPUnit's truncation/concurrent tests at production.

The automated local deployment smoke uses `NOROCEL_DEPLOYMENT_QA=1` and `npx playwright test deployment.spec.ts --project desktop` in frontend. It verifies Nginx/FPM health, protected dotfiles, no-cache SW, authentication, no-store API and report deep-link refresh. Physical devices, TLS/provider mail and the actual target host need their own checks.

## Separately approved migration/cutover

This development request does not authorize production cutover, source deletion or mail to real users. Prepare the following concrete artifacts for that later run:

1. Obtain a consistent Supabase financial/auth snapshot and a real user JSON. Record commit, export timestamps, SHA-256, source schema and counts. Retain encrypted originals with access controls; keep service-role keys server-side.
2. Create the **new isolated staging DB**. Import reviewed trusted identities with stable UUIDs, then per-owner finance dry-runs. Specify the budget month/currency from evidence; inspect every warning/blocker and mapping. Apply only on staging; compare all balance deltas, original transfer sides, monthly per-currency cash flow, goals and settlements. Repeat the same source apply to prove no duplicates.
3. Rehearse recovery from a MySQL consistent snapshot and per-workspace prior backup. Export the complete verified new staging database (including identities, audits, mappings, settings, deduplication/generations). Take an encrypted backup of both the new destination and old source. Define the rollback deadline/operator and exact release/database identities.
4. Agree the maintenance window and freeze **old Supabase writes**, then re-export the final source and reconcile changed hashes on a fresh destination rehearsal. A Laravel maintenance page alone cannot freeze writes from the old application. Any unexplained delta blocks the switch.
5. With separate approval, promote the verified full destination snapshot and new release/configuration, activate HTTPS/SMTP, switch the app origin/document root and invalidate legacy sessions. Do not change APP_ENV to bypass the CLI production guard. Do not replay old client writes. Audit the promoted data before opening access.
6. Trigger the approved reset-password onboarding for selected real users only after test delivery. Unknown random passwords and old Supabase tokens cannot sign in to Laravel. Unverified emails still require verification. Record delivery outcomes without publishing tokens; the development rehearsal sends no real-user email.
7. Open access, monitor failed commands/auth/mail, database availability/lock timeouts and drift; keep the old application/source snapshot recoverable through the agreed acceptance period. Remove legacy deployment only in a separately approved cleanup.

## Rollback and workspace recovery

Before accepting new writes, rollback can point the origin back to the retained legacy release and its untouched source. After new writes, freeze both paths and export the new ledger first: switching to an older database can discard those writes. Reconcile the write interval and obtain the agreed decision before restoring a whole database. Use the recorded release and exact consistent snapshot together; preserve APP_KEY and version-compatible schema. Do not use `migrate:fresh`, disable foreign keys or edit balances by hand.

For one owner's incorrect restore, use the offered **pre-restore backup download**, preview it afresh, inspect balances/warnings and apply with the new revision/key. This is a new atomic restore and generation, not an undo of tombstones. A failed apply already rolls back all financial rows and leaves the prior workspace intact. Expired/stale preview must be recreated; do not override revision checks. For unknown command result retry the same key/payload, then refresh the authoritative balances.

## Operations

Back up MySQL and private storage/APP_KEY regularly, encrypt backups and prove restoration. Keep command tombstones: pruning them can allow a delayed old request to replay after replacement. Expired previews contain private data; cleanup may delete only expired preview rows according to a documented retention policy, never financial rows/audits/prior restore backups without the owner's policy. Limit log retention and access; log mail is development-only and includes reset/verification links. Runtime diagnostics must not log session cookies, passwords or full imported documents.

PWA caches only the static shell and immutable build assets. A new build waits for user acceptance before activation; advise finishing a form before restart. Offline mode disables all financial writes. Snapshot storage is opt-in and contains only timestamp/month/revision/currency totals. Logout clears React Query and IndexedDB immediately; offline logout holds the UI closed and finishes server logout on reconnect. Offline balances are explicitly labelled as a saved copy.
