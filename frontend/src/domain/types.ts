export type Currency = "MDL" | "EUR" | "USD" | "RON";
export type Locale = "ro" | "ru" | "en";
export interface Entity {
  id: string;
  revision: number;
  created_at: string;
  updated_at: string;
}
export interface Account extends Entity {
  name: string;
  kind: "regular" | "savings";
  currency_code: Currency;
  opening_balance_minor: string;
  balance_minor: string;
  include_in_total: boolean;
  archived_at: string | null;
  goal_id: string | null;
}
export interface Category extends Entity {
  name: string | null;
  kind: "income" | "expense";
  system_code: string | null;
  is_system: boolean;
  archived_at: string | null;
}
export interface Operation extends Entity {
  type: "income" | "expense" | "transfer" | "exchange";
  status: "posted" | "voided";
  occurred_on: string;
  description: string;
  amount_minor: string;
  currency_code: Currency;
  account_id: string | null;
  from_account_id: string | null;
  to_account_id: string | null;
  category_id: string | null;
  goal_id: string | null;
  goal_completion_requested: boolean;
  target_amount_minor: string | null;
  target_currency_code: Currency | null;
  quoted_rate: string | null;
  effective_rate: string | null;
  liability_id: string | null;
  history?: unknown[];
}
export interface Goal extends Entity {
  name: string;
  icon: string | null;
  currency_code: Currency;
  target_amount_minor: string;
  savings_account_id: string | null;
  deadline: string | null;
  status: "active" | "reached" | "spent" | "cancelled";
  saved_now_minor: string;
  spent_on_goal_minor: string;
  funded_lifetime_minor: string;
  progress: string;
  legacy_read_only: boolean;
  completed_at: string | null;
}
export interface Liability extends Entity {
  kind: "receivable" | "payable" | "credit";
  counterparty_name: string;
  principal_minor: string;
  currency_code: Currency;
  due_on: string | null;
  comment: string;
  status: "open" | "settled" | "cancelled";
  settlement_operation: Operation | null;
}
export interface Budget extends Entity {
  valuation: Valuation;
  category_id: string;
  period_month: string;
  currency_code: Currency;
  limit_minor: string;
  disabled: boolean;
  fact_minor: string;
  remaining_minor: string;
  progress: string;
  other_currencies: Partial<Record<Currency, string>>;
}
export interface BudgetTemplate extends Entity {
  category_id: string;
  currency_code: Currency;
  limit_minor: string;
  start_month: string;
  stop_month: string | null;
}
export interface Settings {
  locale: Locale;
  base_currency_code: Currency;
  theme: "light" | "dark" | "system";
  timezone: string;
  workspace_revision: number;
  workspace_generation: number;
}
export interface User {
  id: string;
  full_name: string;
  email: string;
  avatar_url: string | null;
  is_admin: boolean;
  billing_plan: "regular" | "premium";
  email_verified_at: string | null;
}
export interface Session {
  user: User;
  settings: Settings;
  pending_email: string | null;
}
export interface Flow {
  income_minor: string;
  expense_minor: string;
  net_minor: string;
}
export interface Balances {
  total_minor: string;
  available_minor: string;
  savings_minor: string;
}
export interface Dashboard {
  valuation_date: string;
  consolidated: { balances: Valuation; cash_flow: Valuation };
  month: string;
  balances: Record<Currency, Balances>;
  cash_flow: Record<Currency, Flow>;
  recent_operations: Operation[];
  goals: Goal[];
  budgets: Budget[];
  liabilities: Liability[];
}
export interface Valuation {
  currency_code: Currency;
  known_subtotal: Record<string, string>;
  incomplete: boolean;
  missing: { source: Currency; target: Currency; requested_on: string }[];
  unconverted: {
    amount_minor: string;
    currency_code: Currency;
    date: string;
    bucket?: string;
  }[];
  meta: {
    rates: {
      id: string;
      provider: string;
      currency_code: Currency;
      effective_on: string;
      requested_on: string;
      version: number;
      fallback: boolean;
    }[];
    cache_key: string;
  };
}
export interface List<T> {
  items: T[];
  pagination: { page: number; pages: number; total: number };
  totals?: Record<
    Currency,
    { receivable_minor: string; payable_minor: string; credit_minor: string }
  >;
}
export interface Envelope<T> {
  data: T;
  meta: { workspace_revision: number; replayed?: boolean };
}
export interface Preview {
  preview_token: string;
  file_hash: string;
  expected_workspace_revision: number;
  expires_at: string;
  counts: Record<string, number>;
  balances: {
    account_id: string;
    name: string;
    currency_code: Currency;
    balance_minor: string;
  }[];
  warnings: {
    code: string;
    field?: string;
    key?: string;
    month?: string;
    currency_code?: string;
    original_rate?: string;
    effective_rate?: string;
  }[];
  blockers: unknown[];
}
