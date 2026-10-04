import { useContext, type ReactNode } from "react";
import { Link } from "react-router-dom";
import {
  ArrowDownLeft,
  ArrowUpRight,
  ArrowLeftRight,
  PiggyBank,
  Wallet,
  ChevronRight,
  Target,
  type LucideIcon,
} from "lucide-react";
import type { Account, Currency, Goal, Operation } from "../domain/types";
import { formatMoney } from "../domain/money";
import { LocaleContext, useT } from "../i18n";
import { Progress } from "./ui";

export type OpenOperation = (
  operation?: Operation,
  toAccount?: string,
  type?: Operation["type"],
) => void;

export function MoneyAmount({
  amount,
  currency,
  className = "",
  approximate = false,
}: {
  amount: string;
  currency: Currency;
  className?: string;
  approximate?: boolean;
}) {
  const locale = useContext(LocaleContext);
  const formatted = formatMoney(amount, currency, locale);
  return (
    <span
      className={`money-amount ${className}`}
      aria-label={(approximate ? "≈ " : "") + formatted}
    >
      {approximate && <span className="approximate">≈ </span>}
      <span>{formatted.slice(0, -4)}</span>{" "}
      <span className="money-currency">{currency}</span>
    </span>
  );
}

export function CurrencyBadge({ currency }: { currency: Currency }) {
  return <span className="currency-tag">{currency}</span>;
}

export function SectionHeading({
  title,
  to,
  children,
}: {
  title: string;
  to?: string;
  children?: ReactNode;
}) {
  const t = useT();
  return (
    <div className="section-heading">
      <h2>{title}</h2>
      {to && (
        <Link to={to}>
          {t("viewAll")}
          <ChevronRight size={16} />
        </Link>
      )}
      {children}
    </div>
  );
}

export function QuickAction({
  icon: Icon,
  label,
  tone = "primary",
  onClick,
}: {
  icon: LucideIcon;
  label: string;
  tone?: string;
  onClick: () => void;
}) {
  return (
    <button className={`quick-action ${tone}`} onClick={onClick}>
      <span>
        <Icon size={23} strokeWidth={1.8} />
      </span>
      {label}
    </button>
  );
}

export const operationActions = [
  { key: "income", icon: ArrowDownLeft, tone: "positive" },
  { key: "expense", icon: ArrowUpRight, tone: "primary" },
  { key: "transfer", icon: ArrowLeftRight, tone: "secondary" },
  { key: "putAside", icon: PiggyBank, tone: "warm" },
] as const;

export function AccountCard({ account }: { account: Account }) {
  const t = useT();
  return (
    <Link
      className={`account-preview currency-${account.currency_code.toLowerCase()}`}
      to={`/operations?account_id=${account.id}`}
    >
      <div className="card-top">
        <span className="entity-icon">
          {account.kind === "savings" ? (
            <PiggyBank size={21} />
          ) : (
            <Wallet size={21} />
          )}
        </span>
        <CurrencyBadge currency={account.currency_code} />
      </div>
      <p>{account.name}</p>
      <MoneyAmount
        amount={account.balance_minor}
        currency={account.currency_code}
      />
      <small>
        {t(account.kind === "savings" ? "savingsKind" : "regular")}
        <ChevronRight size={15} />
      </small>
    </Link>
  );
}

export function GoalPreview({ goal }: { goal: Goal }) {
  const t = useT();
  return (
    <Link className="mini-goal" to="/goals">
      <span className="goal-icon">{goal.icon || <Target size={23} />}</span>
      <div>
        <div className="goal-title">
          <strong>{goal.name}</strong>
          <span>{goal.progress}%</span>
        </div>
        <small>
          {t("savedNow")} ·{" "}
          <MoneyAmount
            amount={goal.saved_now_minor}
            currency={goal.currency_code}
          />
        </small>
        <Progress value={goal.progress} />
        <small>
          {t("target")} ·{" "}
          <MoneyAmount
            amount={goal.target_amount_minor}
            currency={goal.currency_code}
          />
        </small>
      </div>
    </Link>
  );
}

export function StatCard({
  title,
  amount,
  currency,
  icon: Icon,
  tone = "primary",
  incomplete = false,
}: {
  title: string;
  amount: string;
  currency: Currency;
  icon: LucideIcon;
  tone?: string;
  incomplete?: boolean;
}) {
  const t = useT();
  return (
    <section className={`stat-card ${tone}`}>
      <span className="stat-icon">
        <Icon size={21} />
      </span>
      <div>
        <p>{title}</p>
        <MoneyAmount amount={amount} currency={currency} approximate />
        <small>{incomplete ? t("fxIncomplete") : t("monthSummary")}</small>
      </div>
    </section>
  );
}
