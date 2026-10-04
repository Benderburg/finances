# Norocel 2 domain

Every financial row has one UUID owner. Admin has the same ownership restrictions and can only manage profile name/plan/role through a separately logged route. Settings carry workspace revision and generation.

Money is an integer decimal string of minor units (MDL/EUR/USD/RON, scale 2), at most 999999999999999 per value/balance. Reports sum without that record limit. No float arithmetic. Dates are calendar dates; a posted future-dated operation immediately affects current balance.

Balance = opening + posted income − posted expenses − outgoing transfers/exchanges + incoming target amounts. Opening is immutable and excluded from cash flow. One transfer/exchange is one operation. Amend/void validate all old and new account balances. Void retains operation and audit history.

Every mutation locks owner settings before reading financial state, checks key/hash/generation and expected entity revision, writes atomically, then increments workspace revision. Restore increments generation and retains old command tombstones. Reads return one consistent snapshot. Network retries use the same UUID key.

Exchange convention: source units per one target unit; source / quoted rate rounded half up once to receiver minor units. Effective rate is source/actual target to 12 decimal places and must be positive/representable.

Reference valuation is separate: BNM published value/nominal normalized to MDL per unit, immutable versioned records, MDL=1. Cash flow/budgets convert each operation at occurred_on; current balance/savings use chosen valuation date. Cross conversion multiplies source rate and divides target rate, HALF_UP once per row before arbitrary-size aggregation. Only past effective dates up to 7 days old are eligible. Missing observations preserve original amounts and mark consolidated values incomplete. Changing display/base currency never changes stored money. No external provider fetch in a financial transaction.

CSV is additive, versioned and separate from JSON restore. Preview confirms encoding/delimiter/mapping, owner/currency/link constraints and strict/possible duplicates. Apply is one serialized command, checks generation/revision and final balances, writes audit/context links and durable row receipts atomically. Identical purchases may be explicitly included; repeating the same upload or unique transaction ID cannot duplicate records. Safe export text escaping is reversible. CSV context never creates goals/liabilities by name; full entity/history restore uses JSON.

Goal = one unique savings account in the same currency. Saved is current account balance; spent is posted goal expenses; lifetime funded = saved + spent. Cancellation takes priority over completion marker/spent >= target, then funded >= target, then active. Unlinked legacy goals are read-only and never synthesize money.

Liabilities do not create money. A full settlement is one income (receivable) or expense (payable/credit), linked uniquely and atomically. Financial editing is prohibited outside the liability command. Voiding settlement also unlinks it and reopens the obligation, subject to balance checks.

Budget = expense category + month + fixed currency. Fact converts each posted expense on its occurred_on using stored reference observations, then sums. Missing rates produce an explicitly incomplete known subtotal; original currencies remain visible. Templates materialize through an explicit idempotent command. Disabled monthly overrides survive subsequent ensure commands. Archiving categories stops future templates.

Backup is a strict version 2 snapshot. Preview validates every row, link, rate, balance, settlement and limit without touching financial rows. Apply uses a server-owned expiring preview, rechecks workspace revision, records a recoverable prior snapshot and replaces in one transaction. Identity, credentials and privileges are excluded.
