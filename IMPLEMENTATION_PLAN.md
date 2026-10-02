# Norocel 2 implementation

Source: `master` at `25d3a31`, exactly the audit's commit. No AGENTS.md exists. The legacy application in the repository root is retained. New application: `backend/` (Laravel) and `frontend/` (React).

## Environment and decisions

- Production database: MySQL 8.4 LTS, InnoDB, utf8mb4. No PostgreSQL or SQLite substitute for financial integration tests.
- Local PHP is 8.2.12 with PDO MySQL, BCMath, intl and mbstring. Laravel 12 is compatible with PHP 8.2 and receives security fixes through 2027-02-24 ([support policy](https://laravel.com/docs/12.x/releases)). Host PHP/version/access has been requested; compatibility on the actual host remains unverified. Upgrade PHP/Laravel together when the host supports PHP 8.3+.
- Node 22.14, strict TypeScript, React, Vite, Router, TanStack Query. Locked package versions; no production Node process.
- One origin; document root `backend/public`. API `/api/v1`, session auth `/auth`, CSRF `/sanctum`. Build assets to Laravel public directory.
- File session/cache drivers and log mail in development; production mail and HTTPS settings must be supplied by the owner.

## Sequence

1. Database, exact money/rates, workspace lock, deduplication, revisions and commands; MySQL integration tests.
2. Goals/settlements/budgets/reports/auth/admin and strict versioned preview/restore.
3. Responsive React forms and query invalidation, PWA shell and opt-in summary.
4. Legacy adapters, fixture rehearsal, migration reconciliation and UI QA; release/runbook documents.

Stage B (BNM and CSV) is a subsequent release after acceptance of A. Define interfaces now without exposing inactive controls.

## Verified legacy format

`assets/js/supabase-api.js::exportUserData` exports profile, accounts, categories, transactions, budgets, goals, liabilities. Profile is the hydrated camelCase object returned by `mapProfileFromDb` (the audit/spec claim that it is snake_case is contradicted by this commit's code); accept both shapes and warn about discarded identity/privilege fields.

- account: id/name/type/currencyCode/openingBalance/includeInTotal/createdAt.
- category: id/key/name/type/createdAt.
- transaction: id/amount/convertedAmount/exchangeRate/desc/date/category/type/accountId/fromAccountId/toAccountId/currencyCode/convertedCurrencyCode/goalId.
- goal: id/name/target/saved/icon/deadline/currencyCode/savingsAccountId/status/completedAt.
- liability: id/counterpartyName/amount/currencyCode/type/dueDate/comment/status/settlementAccountId/settlementTransactionId/settledAt/createdAt.
- budgets: object mapping category key to monthly limit. An explicit export month and profile currency are required for conversion.

Read JSON number lexemes as decimal strings before JSON decoding; no float conversion. Server exports use snake_case database rows, require explicit owner selection, and never supply trusted identities through the public restore API.

## External dependencies / blockers

Actual hosting versions, live schema, server export and user backup have not been supplied. Fixtures prove adapter behavior only, not production migration. Do not silently repair negative balances, broken settlements, currency conflicts or missing accounts. No production cutover, source deletion or real-user mail is part of development.
