# Migration rehearsal — 2026-10-02

## Evidence and scope

Repository source: `Benderburg/finances`, `master` commit `25d3a31`, matching the supplied audit. Legacy source files retained. No live Supabase connection, real server export or real user JSON was supplied. **Production balances, live schema and real identity counts have not been verified.** The results below are a local MySQL 8.4 rehearsal with explicit fixtures, not a production migration report.

Implementation: `backend/app/Migration/ExactJson.php`, `LegacyNorocelAdapter.php`, `SupabaseExportAdapter.php`, `app/Console/Commands/MigrateNorocel.php`, `ImportIdentities.php`. Adapter reads numeric lexemes as exact decimal strings before JSON decoding; no binary float conversion. v2 minor money stays strings. UUIDs are preserved unless they collide with another destination workspace; explicit mapping is stored per run. No synthetic balancing transactions, no deletion at source.

## Actual legacy shape

`assets/js/supabase-api.js::exportUserData` in this commit emits accounts/categories/transactions/goals/liabilities in camelCase; budgets are a category-key object. Its profile is the hydrated object from `mapProfileFromDb`, with camelCase name/avatar/admin/billing fields. The audit's claim of a snake_case profile is not accurate for this commit. Adapter excludes both spelling variants of identity/privilege fields and accepts the common preference fields language/currency. Server adapter converts the actual snake_case database columns to the same canonical legacy shape.

| Legacy field | New representation |
|---|---|
| account openingBalance | exact opening_balance_minor; excluded from income |
| transaction amount / convertedAmount | original debit / credit minor strings, one operation |
| exchangeRate | original metadata if inconsistent; effective rate derived from unchanged amounts |
| category key | owner category UUID; system codes recognized in old RO/RU/EN spellings |
| deleted custom category key | archived placeholder with original key; warning, history retained |
| goal savingsAccountId | proven savings account linkage; no new balance invented |
| unlinked goal saved/status | read-only legacy state; no cash operation synthesized |
| liability settlement IDs | linked only when operation/account/type/currency/full amount agree; otherwise blocker |
| budgets object | explicit `--month=YYYY-MM` and profile currency → month override + template |
| email/name/avatar/admin/billing/password | excluded from public financial restore with warnings; trusted identities handled separately |

Unsupported/missing currencies, >2-decimal money, unrepresentable rates, negative final balances, missing accounts/goals, contradictory links, unknown JSON schema, missing budget month/currency and unproven settlements block apply. Invalid import does not wipe a workspace. Date-only fields remain date-only; exact source timestamps and original notes remain intact.

## Fixture results

Fixture: `backend/tests/fixtures/legacy-workspace.json`. SHA-256:
`a516c2fd6a61d4f37b7596b02149669d59e1093d4b3c76f1f66c8c7d373f5f14`.

Evidence: `migration-fixture-dry-run.json`, `migration-fixture-applied.json`, `migration-fixture-replayed.json`.

| Account | Old balance minor | New balance minor | Delta |
|---|---:|---:|---:|
| MDL cash `22222222-2222-4222-8222-222222222222` | 15000 | 15000 | 0 |
| USD account `33333333-3333-4333-8333-333333333333` | 10000 | 10000 | 0 |

Three operations: one income, one expense, one exchange. Exchange preserves **180000 MDL minor → 10000 USD minor**; effective rate `18.000000000000`. The old rate `17.5` is retained in metadata and flagged. It never changes either amount. Removed category is an archived placeholder. Budget assumes month **2026-10**, currency **MDL**, visibly recorded in warnings. Privilege fields are excluded. No blockers in this fixture.

The file was dry-run, atomically applied to a dedicated fake owner, then applied again. Second apply reports `replayed=true`, still three operations. A run stores source hash, mapping and prior workspace backup. Password/admin/billing from the public fixture did not modify the destination identity.

Migration replay also checks the explicit source owner and budget-month assumptions; the same file cannot be replayed with a different interpretation. The deterministic migration command uses the same workspace lock, idempotency key and generation checks as HTTP writes. Retrying an already applied migration after a workspace restore returns WORKSPACE_REPLACED, rather than returning an obsolete success or applying it again. Both cases are covered by MySQL tests.

Automated tests additionally prove blockers for fractional-cent money, currency mismatch and broken settlement; mid-insert restore failure rolls back all deletes/inserts; stale preview cannot apply; old command retry after restore gets WORKSPACE_REPLACED. Cross-owner v2 restore remaps IDs/references while preserving arbitrary free text equal to an old UUID, archive flags, voided rows and audit history.

## Server and identity formats

Trusted **financial server export** is a JSON wrapper:

```json
{"format":"norocel.supabase-export","profiles":[],"accounts":[],"categories":[],"transactions":[],"goals":[],"liabilities":[],"budgets":[]}
```

Rows use columns in the existing `supabase/schema.sql` plus subsequent migrations: account currency_code/opening_balance/include_in_total; transaction transaction_date/description/account_id/from_account_id/to_account_id/currency_code/converted_amount/converted_currency_code/exchange_rate/goal_id; goal target_amount/saved_amount/savings_account_id/status/completed_at; liability liability_type/counterparty_name/amount/currency_code/settlement_account_id/settlement_transaction_id; budget category/monthly_limit. Owner column is user_id; profile owner id. Select one owner explicitly:

```powershell
php artisan norocel:migrate server-export.json --source-user=SOURCE_UUID --month=2026-10 --report=server-dry-run.json
```

Use database-native exact decimal strings or unrounded JSON numbers. Do not serialize via JavaScript Number first. Export the complete related tables from one quiescent snapshot; never give a browser a Supabase service-role secret.

Trusted **identity export** is a different server-only file, never a public backup:

```json
{"format":"norocel.supabase-identities","users":[{"id":"SOURCE_UUID","email":"member@example.test","email_confirmed_at":"2026-10-01T00:00:00Z","full_name":"Member","billing_plan":"regular","is_admin":false}]}
```

Collect auth UUID/email/confirmed-at from the server export and profile name/plan/admin from trusted profile rows. Review admin/plan mapping before loading. `norocel:identities file.json` is dry-run; `--apply` is a local/staging rehearsal. Duplicate UUID/email and destination conflicts are blockers. Public registration/profile/import cannot choose UUID or roles. Identity test preserves the supplied UUID and flags, initializes exactly15 system categories, repeats without duplicates and sends **zero emails**. Laravel gets a fresh unknown random password; Supabase credentials/sessions are not imported. Users onboard through the working reset-password flow after the separately approved cutover and SMTP check.

Both CLI apply commands refuse APP_ENV=production. The release procedure promotes the reconciled staging database through an approved cutover, preserving verified UUIDs, roles, money, mappings and command tombstones. See RUNBOOK.md; the existing Supabase source remains recoverable.

## Required real-data acceptance

Obtain one trusted server snapshot and at least one real user backup; verify schema against the exports, exact amounts, account currencies, transfer pairs, goal markers, removed keys and liability links. Run every owner separately, review all warnings/assumptions, ensure account deltas zero, compare operation counts by type, monthly cash flow per currency and saved/spent/funded goal projections. Every blocker needs a source-backed resolution; invented balancing operations are prohibited. Repeat apply, review ID mapping and rehearse rollback before scheduling a production switch. None of these live-data steps has been claimed complete.
