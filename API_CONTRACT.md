# Norocel API v1 / workspace JSON v2

## Transport and authorization

One origin, cookie session. Call `GET /sanctum/csrf-cookie`, send cookies and `X-XSRF-TOKEN` (decoded `XSRF-TOKEN` cookie) for mutations, `Accept: application/json`. No browser bearer token. Production cookie is HttpOnly, Secure, SameSite=Lax. Private API/auth/downloads have `Cache-Control: no-store, private`. Verified email is required for finance/admin endpoints; `/me` is available before verification. Ownership comes from the authenticated session. Foreign resource UUIDs return 404, including to administrators.

Every `/api/v1` mutation requires `Idempotency-Key: <UUID>`. Keep the key and exact payload until a definite response. Retries after network loss/timeout/5xx use the same key. Same owner/key/command/payload returns the stored result; different payload or route returns 409 `IDEMPOTENCY_CONFLICT`. Entity updates/lifecycle commands also require `expected_revision` (integer >=1); stale values return 409. Restore uses `expected_workspace_revision` instead. Successful commands increment workspace revision; restore also increments generation. Old keys from an earlier generation return 409 `WORKSPACE_REPLACED` and are retained as tombstones.

Financial success (including create, status 200):

```json
{"data": {}, "meta": {"workspace_revision": 42, "replayed": false}}
```

Reads are consistent snapshots and return the same envelope, without `replayed`. Lists: `data.items`, `data.pagination={page,pages,total}`. `page=1`, `per_page=30`, maximum 100. Stable operations order: occurred_on DESC, created_at DESC, id DESC; other resources created_at DESC, id DESC. Queries never select another owner. Admin stats/users and auth responses use `{"data":...}` without finance snapshot metadata. Export returns the document directly as an attachment.

Error:

```json
{"error":{"code":"INSUFFICIENT_FUNDS","message":"INSUFFICIENT_FUNDS","fields":{},"details":{"account_id":"UUID","currency_code":"MDL","would_be_balance_minor":"-1"}}}
```

Status: 401 unauthenticated, 403 forbidden/unverified/admin required, 404 missing/foreign, 419 CSRF expired, 422 invalid data/schema, 409 revision/key/negative-balance conflict, 429 rate limited, 500 storage failure with generic public error. Codes include `VALIDATION_FAILED`, `UNKNOWN_FIELDS`, `INVALID_MONEY`, `CURRENCY_MISMATCH`, `ACCOUNT_ARCHIVED`, `CATEGORY_ARCHIVED`, `ENTITY_IN_USE`, `FX_AMOUNT_MISMATCH`, `STALE_REVISION`, `STALE_WORKSPACE`, `PREVIEW_EXPIRED`, `WORKSPACE_REPLACED`. Validation field keys and error codes are translated by the UI; database diagnostics are not public responses.

## Primitives and common fields

- UUID identifiers; record `revision` is a JSON integer. No user-supplied `user_id`, balance, status, privileges or derived effective rate.
- Money `*_minor`: canonical base-10 integer **string**, `"1"` through `"999999999999999"`. Opening and legacy saved values may be `"0"`. Currency enum `MDL|EUR|USD|RON`, scale 2. Totals can exceed the individual-value limit; net/history/remaining totals may be negative strings. JSON numbers for minor money are rejected.
- Rates: positive decimal strings, at most 12 fractional places, maximum `999999999999999999.999999999999`. Convention: source units per 1 target unit. `source_minor / quoted_rate` rounds half up once to target minor units. Effective rate comes from the actual debit/credit to 12 places. Quoted+received disagreement is rejected.
- `occurred_on`, `deadline`, `due_on`: calendar `YYYY-MM-DD`. Future posted entries immediately affect current balance. UTC timestamps are returned as SQL UTC timestamps or ISO-8601 (user/auth timestamps); calendar dates are not shifted by timezone. Every month field is the first day (`YYYY-MM-01`); query `month` is `YYYY-MM`.
- Names max 255, description/comment max 2000, goal icon max 32; user text is escaped by React.

## Auth

Auth POST responses have `data` (registration 201, other successes 200). Throttle: 10 requests/minute per IP. Password min 12, confirmation required.

| Method/path | Body / behavior |
|---|---|
| POST `/auth/register` | full_name, email, password, password_confirmation; creates workspace, session, verification notification |
| POST `/auth/login` | email, password; regenerates session, updates last_seen |
| POST `/auth/logout` | {}; invalidates session/token |
| POST `/auth/forgot-password` | email; generic `RESET_LINK_SENT`, same response for unknown address |
| POST `/auth/reset-password` | email, token, password, password_confirmation; `PASSWORD_RESET`; token expiry 60min, single use |
| GET `/auth/verify-email/{id}/{hash}` | signed URL, expires; checks hash, verifies email, redirects `/` |
| POST `/auth/resend-verification` | authenticated; sends verification if needed |
| POST `/auth/change-password` | current_password, password, password_confirmation; checks existing password; old password sessions invalidated |
| POST `/auth/email-change` | current_password, email; old email retained until new address confirms; response pending_email |
| GET `/auth/confirm-email/{id}/{hash}` | signed URL valid 60min; verifies pending address, consumes pending state; redirect `/settings` |

## Profile and dashboard

`GET /api/v1/me`: `{user,settings,pending_email}`. User: id, full_name, email, avatar_url, billing_plan, is_admin, email_verified_at, last_seen_at, timestamps; password/remember_token hidden. Settings: locale `ro|ru|en`, base_currency_code, theme `light|dark|system`, timezone (IANA), workspace_revision, workspace_generation, user_id. `/me` PATCH allowlist: full_name, avatar_url (nullable http/https max2048), locale, base_currency_code, theme, timezone. Changing base currency only changes ordering/defaults; no financial conversion.

`GET /dashboard?month=YYYY-MM`: month, balances by currency `{total_minor,available_minor,savings_minor}`, cash_flow by currency `{income_minor,expense_minor,net_minor}`, recent_operations, goals, budgets, open liabilities. Current totals include archived accounts with include_in_total=true, regardless of selected month; regular/savings separate. Transfer/exchange never contributes to income/expense.

## Resource routes

Prefix `/api/v1`. All seven resources support GET list, GET `/{id}`, POST create, PATCH `/{id}`. DELETE exists only for accounts/categories/goals/budgets. PATCH is partial except dedicated commands. Unknown body keys are rejected. Return projected entity, except operations return `{operation,affected_balances}`. Common entity fields: id, revision, created_at, updated_at; owner omitted.

| Resource | Create fields (required in bold); partial PATCH restrictions |
|---|---|
| accounts | **name, kind (regular/savings), currency_code, opening_balance_minor**; include_in_total optional. Opening/currency immutable; goal-linked kind immutable. Projection adds balance_minor, goal_id, archived_at |
| categories | **name, kind (income/expense)**. Names unique by owner/kind/casefold; used kind immutable. System goal_expense protected; other system labels may be renamed/archived. Projection is_system, system_code, archived_at |
| goals | **name, target_amount_minor, currency_code**; savings_account_id nullable or creates new zero savings account atomically (new_account_name optional), deadline/icon nullable. Currency/link immutable. Projection saved_now_minor, spent_on_goal_minor, funded_lifetime_minor, progress (decimal % string), status active/reached/spent/cancelled, completed_at, legacy_read_only |
| liabilities | **kind (receivable/payable/credit), counterparty_name, principal_minor, currency_code**; due_on nullable, comment optional. Settled kind/principal/currency immutable. Projection status, settlement_operation; list also has `totals[currency].receivable_minor/payable_minor/credit_minor` for all open entries, independent of pagination |
| budgets | **category_id, period_month, currency_code, limit_minor**; expense category. Category/month immutable. Projection disabled, source_template_id, fact_minor, remaining_minor, progress, other_currencies. Delete creates disabled override |
| budget-templates | **category_id, start_month, currency_code, limit_minor**; stop_month nullable/exclusive, >= start. Category immutable. Same category intervals cannot overlap; materialization explicit |
| operations | **type, occurred_on, amount_minor**; description optional. income/expense require account_id/category_id, optional matching currency_code. transfer requires from_account_id/to_account_id in same currency, target amount if supplied equals debit. exchange requires different-currency accounts plus target_amount_minor or quoted_rate (or both if consistent); currencies derived from accounts. Unused single/pair fields null. Single-vs-pair class and income-vs-expense immutable on edit; pair type follows selected currencies. Archived accounts reject financial changes |

Operations list filters: type, account_id (any side), category_id, goal_id, liability_id, status posted/voided, date_from/date_to, q in description. Accounts/categories: archive active/archived/all, kind. Budgets: month. All foreign filter references are owner checked. Detail operation includes history: id, operation_id, actor_id, action created/amended/voided, before_payload (nullable), after_payload, UTC timestamps. Audit snapshots use original money strings.

| Command POST path (PATCH explicitly marked) | Body / response |
|---|---|
| `accounts/{id}/archive`, `/unarchive`; categories equivalent | expected_revision; preserves balances/history |
| `operations/{id}/void` | expected_revision; posted effect excluded, audit retained; checks all affected balances |
| `goals/{id}/cancel`, `/resume` | expected_revision; no movement of money |
| `goals/{id}/spend` | expected_revision, amount_minor, occurred_on, optional description/goal_completion_requested; `{operation,affected_balances,goal}` |
| PATCH `goals/{id}/expenses/{operation}` | same plus expected_operation_revision; purpose/account/category remain bound |
| `liabilities/{id}/cancel`, `/resume` | expected_revision, only unsettled |
| `liabilities/{id}/settle` | expected_revision, account_id, category_id, occurred_on, optional description; amount fixed to principal; `{operation,affected_balances,liability}` |
| `liabilities/{id}/void-settlement` | expected_revision; void effect and reopen atomically; direct linked operation edits/voids rejected |
| `budgets/ensure-month` | period_month; `{created: integer}`; no duplicates, archived categories skipped, disabled overrides retained |
| `budget-templates/{id}/stop` | expected_revision, stop_month; future existing months not rewritten |

DELETE body: expected_revision. Accounts require zero opening and no operations/goal links. Category must be custom and unused. Goal requires no operations touching its account or goal and zero balance; savings account is retained. Monthly budget becomes disabled. Operations use void; liabilities use cancel. No cascade deletion of financial history.

## Reports

GET `/reports/cash-flow`, `/reports/expenses`, `/reports/balances`; 60/minute per owner. Query month defaults current local month; date_from/date_to override six-month default, max366days, from<=to. Currency filter enum. Returns date_from/date_to/generated_at.

- cash-flow: currency totals and monthly `{month,currencies}`; opening/transfer/exchange/voided excluded.
- expenses: `{categories:[{category_id,currency_code,amount_minor}]}` sorted descending; optional currency_code, goal_only=1, exclude_settlements=1; includes posted goal expenses/full settlements by default.
- balances: `{reconstructed_history:true,accounts:[{account_id,name,currency_code,points:[{date,balance_minor}]}]}`; opening + posted dated operations, preserves negative reconstructed history; current balance invariant is separate.

Print layouts include selected currency, period, generation time and original values; currencies never silently added together.

Stage B: dashboard accepts `display_currency=MDL|EUR|USD|RON`, `valuation_date=YYYY-MM-DD` and adds `consolidated.{balances,cash_flow}`. Reports accept `display_currency`; cash-flow adds consolidated totals/months, expenses consolidated category buckets, balances `consolidated_points`. Valuations contain `currency_code`, string `known_subtotal`, `incomplete`, `missing[{source,target,requested_on}]`, original `unconverted`, and `meta.{rates,context,cache_key}`. Rate metadata carries immutable record IDs/versions, requested/effective dates and fallback. Missing rates never imply zero original money. Budgets add `valuation`; fact uses per-operation historical conversion to the fixed budget currency.

## Reference rates / CSV (Stage B)

All endpoints below require verified session/CSRF for POST, private no-store responses. FX/report routes use reports limiter (60/min); CSV POST uses imports limiter (5/min).

| Route | Contract |
|---|---|
| GET `/fx/reference` | required date YYYY-MM-DD; optional refresh=1 fetches BNM outside financial transactions; returns rates and fetch availability |
| POST `/operations/quote` | amount_minor string, currency_code, target_currency_code, date; indicative preview only, amount_minor nullable + missing/rates/incomplete |
| GET `/operations/export.csv` | full journal CSV v1 attachment; safe=1 default, safe=0 raw; reversible formula protection |
| POST `/csv/preview` | multipart file <=5MiB and JSON-string options; initial detection returns headers/sample/source_accounts/count/encoding/delimiter/needs_confirmation; confirmed mapping returns owner-bound preview_id/workspace_revision/rows |
| POST `/csv/apply` | command key, preview_id UUID, selected_rows integer array, accept_possible_duplicates boolean; atomic additive batch; returns imported/operation_ids/affected_balances |

Preview options: `confirmed` boolean, encoding, delimiter, mapping object (date/amount/direction/currency/description/category/transaction_id → header), account_id, income_category_id/expense_category_id, date_format, decimal_separator, income_value/expense_value; own format supports account_mappings (source UUID → existing owned UUID). Limits: 10,000 data rows, 30-minute preview. Mapping and row errors precede financial commit; strict duplicates cannot be selected; possible duplicates require explicit confirmation. Source-row receipts and command tombstones survive workspace replacement. See [Stage B](docs/STAGE_B.md) for encoding, safe CSV format, link constraints and rate-sync CLI.

## Backup / restore

| Route | Contract |
|---|---|
| GET `/backup/export` | consistent version2 document; JSON attachment |
| POST `/backup/preview` | multipart file, legacy_month optional YYYY-MM; 5/minute; 10MiB max, 50k operations, 100k rows total; validates without financial mutation |
| POST `/backup/apply` | preview_token UUID, expected_workspace_revision integer, command key; locked revalidation, previous snapshot, complete replacement in one transaction |
| GET `/backup/previous/{id}` | owner-only pre-restore JSON attachment |

Preview data: preview_token, SHA-256 file_hash, expected_workspace_revision, expires_at (30min), counts by table, balances [{account_id,name,currency_code,balance_minor}], warnings [{code,...context}], blockers [], legacy reconciliation if applicable. Invalid documents return an error and cannot apply. Apply: manifest, explicit id_remapping, previous_backup_id, dashboard; revision/generation advance. UUIDs colliding with another workspace are remapped on IDs/references/audit references only; free text never changed.

Version2 top-level **exact** fields: schema=`norocel.workspace`, version=2 (integer), exported_at, settings, accounts, categories, goals, operations, operation_revisions, budget_templates, budgets, liabilities, liability_settlements. Settings exactly locale/base_currency_code/theme/timezone; no revision/owner/credentials. Each table is an array with common id/revision/created_at/updated_at plus allowlist below. IDs globally unique inside the document; all ownership links internal, goal/account currencies agree, exactly all15 system categories, linked settlement shape proven, final balances >=0 and <=maximum. Archive/void data retained.

| Table | Allowed fields beyond common |
|---|---|
| accounts | name,kind,currency_code,opening_balance_minor,include_in_total,archived_at |
| categories | kind,system_code,name,is_system,archived_at,legacy_metadata |
| goals | name,icon,target_amount_minor,currency_code,deadline,savings_account_id,cancelled_at,legacy_saved_minor,legacy_status,legacy_completed_at |
| operations | type,status,occurred_on,description,account_id,from_account_id,to_account_id,amount_minor,currency_code,target_amount_minor,target_currency_code,quoted_rate,effective_rate,category_id,goal_id,goal_completion_requested,voided_at,legacy_metadata |
| operation_revisions | operation_id,action,before_payload,after_payload (operation allowlist only); actor always destination owner on restore |
| budget_templates | category_id,currency_code,limit_minor,start_month,stop_month |
| budgets | category_id,currency_code,limit_minor,period_month,disabled,source_template_id |
| liabilities | kind,counterparty_name,principal_minor,currency_code,due_on,comment,cancelled_at |
| liability_settlements | liability_id,operation_id |

Version2 identity/role/password/billing fields anywhere outside explicit legacy_metadata are rejected. Legacy adapter warnings/exclusions and server-only identity format are specified in MIGRATION_REPORT.md. Public preview never treats a server identity file as a workspace.

## Admin

GET `/admin/stats`: total, active_30_days, retention_percent (active30/total), premium, admins. GET `/admin/users?q=&page=`: paginated profile allowlist id/email/full_name/billing_plan/is_admin/last_seen_at/created_at. PATCH `/admin/users/{id}`: only full_name, billing_plan regular/premium, is_admin boolean; command key, admin check before and during transaction, subject workspace lock, separate before/after audit. No financial cross-owner access or credentials in the response.
