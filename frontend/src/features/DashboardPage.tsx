import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import {
  ArrowDownLeft,
  ArrowUpRight,
  ArrowLeftRight,
  ChevronRight,
  PiggyBank,
  Wallet,
} from "lucide-react";
import type {
  Account,
  Category,
  Currency,
  Dashboard,
  Session,
} from "../domain/types";
import { currencies, today } from "../domain/money";
import { get } from "../data/api";
import { categoryName, useT } from "../i18n";
import { Empty, Progress, State, useAllList } from "../components/ui";
import {
  AccountCard,
  CurrencyBadge,
  GoalPreview,
  MoneyAmount,
  QuickAction,
  SectionHeading,
  StatCard,
  operationActions,
  type OpenOperation,
} from "../components/finance-ui";
import { OperationRow } from "./FinancePages";
import { FxRefresh, ValuationNote } from "./FxViews";

export function DashboardPage({
  session,
  openOperation,
  onSave,
}: {
  session: Session;
  openOperation: OpenOperation;
  onSave: () => void;
}) {
  const t = useT();
  const [valuationOpen, setValuationOpen] = useState(false);
  const [month, setMonth] = useState(
    today(session.settings.timezone).slice(0, 7),
  );
  const [displayCurrency, setDisplayCurrency] = useState<Currency>(
    session.settings.base_currency_code,
  );
  const [valuationDate, setValuationDate] = useState(
    today(session.settings.timezone),
  );
  const q = useQuery({
    queryKey: ["dashboard", month, displayCurrency, valuationDate],
    queryFn: () =>
      get<Dashboard>(
        `/dashboard?month=${month}&display_currency=${displayCurrency}&valuation_date=${valuationDate}`,
      ),
  });
  const ac = useAllList<Account>("/accounts"),
    cats = useAllList<Category>("/categories");
  const accounts = ac.data?.data.items.filter((a) => !a.archived_at) ?? [];
  const order = [
    session.settings.base_currency_code,
    ...currencies.filter((c) => c !== session.settings.base_currency_code),
  ];
  return (
    <>
      <div className="page-heading dashboard-heading">
        <div>
          <p className="eyebrow">
            {t("hello")}, {session.user.full_name.split(" ")[0]}
          </p>
          <h1>{t("overview")}</h1>
          <p className="page-description">{t("dashboardHint")}</p>
        </div>
        <input
          aria-label={t("month")}
          type="month"
          value={month}
          onChange={(e) => {
            if (e.target.value) setMonth(e.target.value);
          }}
        />
      </div>
      <State query={q}>
        {(d) => (
          <>
            <div className="dashboard-hero">
              <section className="hero-balance">
                <div className="card-top">
                  <span>
                    <Wallet size={18} />
                    {t("available")}
                  </span>
                  <CurrencyBadge currency={displayCurrency} />
                </div>
                <MoneyAmount
                  className="hero-money"
                  amount={
                    d.consolidated.balances.known_subtotal.available_minor
                  }
                  currency={displayCurrency}
                  approximate
                />
                <p className="hero-caption">
                  {t(
                    d.consolidated.balances.incomplete
                      ? "fxKnownBalance"
                      : "estimateHint",
                  )}
                </p>
                <div className="hero-split">
                  <div>
                    <small>{t("total")}</small>
                    <MoneyAmount
                      amount={
                        d.consolidated.balances.known_subtotal.total_minor
                      }
                      currency={displayCurrency}
                      approximate
                    />
                  </div>
                  <Link to="/savings">
                    <small>
                      <PiggyBank size={15} />
                      {t("savings")}
                    </small>
                    <MoneyAmount
                      amount={
                        d.consolidated.balances.known_subtotal.savings_minor
                      }
                      currency={displayCurrency}
                      approximate
                    />
                  </Link>
                </div>
                {d.consolidated.balances.incomplete && (
                  <span className="hero-warning">{t("fxIncomplete")}</span>
                )}
              </section>
              <section className="quick-panel">
                <p className="eyebrow">{t("quickActions")}</p>
                <div className="quick-actions">
                  {operationActions.map(({ key, icon, tone }) => (
                    <QuickAction
                      key={key}
                      label={t(key)}
                      icon={icon}
                      tone={tone}
                      onClick={() =>
                        key === "putAside"
                          ? onSave()
                          : openOperation(undefined, undefined, key)
                      }
                    />
                  ))}
                </div>
                <p className="hint">{t("neutralHint")}</p>
              </section>
            </div>
            <div className="dashboard-grid">
              <div className="dashboard-main">
                <section className="dashboard-section">
                  <SectionHeading title={t("myAccounts")} to="/accounts" />
                  <State query={ac}>
                    {() =>
                      accounts.length ? (
                        <div className="account-strip">
                          {accounts.slice(0, 4).map((a) => (
                            <AccountCard key={a.id} account={a} />
                          ))}
                        </div>
                      ) : (
                        <div className="panel">
                          <Empty
                            title="emptyAccounts"
                            hint="emptyAccountsHint"
                            action={
                              <Link
                                className="button primary"
                                to="/accounts?new=1"
                              >
                                {t("createAccount")}
                              </Link>
                            }
                          />
                        </div>
                      )
                    }
                  </State>
                </section>
                <section className="panel transactions-panel">
                  <SectionHeading title={t("recent")} to="/operations" />
                  {d.recent_operations.length ? (
                    d.recent_operations
                      .slice(0, 6)
                      .map((o) => (
                        <OperationRow
                          key={o.id}
                          o={o}
                          accounts={ac.data?.data.items}
                          categories={cats.data?.data.items}
                          onClick={() => openOperation(o)}
                        />
                      ))
                  ) : (
                    <Empty
                      title="emptyOperations"
                      hint="emptyOperationsHint"
                      action={
                        <button
                          className="primary"
                          onClick={() => openOperation()}
                        >
                          {t("newOperation")}
                        </button>
                      }
                    />
                  )}
                </section>
                <section className="panel">
                  <SectionHeading title={t("currencyBreakdown")} />
                  <p className="hint">{t("originalCurrencies")}</p>
                  <div className="currency-balances">
                    {order.map((c) => (
                      <div className="currency-balance" key={c}>
                        <CurrencyBadge currency={c} />
                        <MoneyAmount
                          amount={d.balances[c].total_minor}
                          currency={c}
                        />
                        <small>
                          {t("available")} ·{" "}
                          <MoneyAmount
                            amount={d.balances[c].available_minor}
                            currency={c}
                          />
                        </small>
                        <small>
                          {t("savings")} ·{" "}
                          <MoneyAmount
                            amount={d.balances[c].savings_minor}
                            currency={c}
                          />
                        </small>
                      </div>
                    ))}
                  </div>
                </section>
              </div>
              <div className="dashboard-aside">
                <section className="panel month-panel">
                  <SectionHeading title={t("flow")} to="/reports" />
                  <p className="hint">
                    {month} · {displayCurrency}
                  </p>
                  <StatCard
                    title={t("incomes")}
                    amount={
                      d.consolidated.cash_flow.known_subtotal.income_minor
                    }
                    currency={displayCurrency}
                    icon={ArrowDownLeft}
                    tone="positive"
                    incomplete={d.consolidated.cash_flow.incomplete}
                  />
                  <StatCard
                    title={t("expenses")}
                    amount={
                      d.consolidated.cash_flow.known_subtotal.expense_minor
                    }
                    currency={displayCurrency}
                    icon={ArrowUpRight}
                    tone="warm"
                    incomplete={d.consolidated.cash_flow.incomplete}
                  />
                  <div className="net-flow">
                    <span>
                      <ArrowLeftRight size={16} />
                      {t("flow")}
                    </span>
                    <MoneyAmount
                      amount={d.consolidated.cash_flow.known_subtotal.net_minor}
                      currency={displayCurrency}
                      approximate
                    />
                  </div>
                  <ValuationNote value={d.consolidated.cash_flow} />
                </section>
                <section className="panel">
                  <SectionHeading title={t("goals")} to="/goals" />
                  {d.goals.length ? (
                    d.goals
                      .slice(0, 3)
                      .map((g) => <GoalPreview key={g.id} goal={g} />)
                  ) : (
                    <Empty
                      title="emptyGoals"
                      hint="emptyGoalsHint"
                      action={
                        <Link className="button" to="/goals?new=1">
                          {t("createGoal")}
                        </Link>
                      }
                    />
                  )}
                </section>
                <section className="panel">
                  <SectionHeading title={t("budgets")} to="/budgets" />
                  {d.budgets.length ? (
                    d.budgets.slice(0, 3).map((b) => {
                      const cat = cats.data?.data.items.find(
                        (c) => c.id === b.category_id,
                      );
                      return (
                        <Link
                          to="/budgets"
                          className="budget-preview"
                          key={b.id}
                        >
                          <div className="summary-line">
                            <span>
                              {cat ? categoryName(cat, t) : t("category")}
                            </span>
                            <strong>{b.progress}%</strong>
                          </div>
                          <Progress value={b.progress} />
                          <small>
                            <MoneyAmount
                              amount={b.fact_minor}
                              currency={b.currency_code}
                            />{" "}
                            /{" "}
                            <MoneyAmount
                              amount={b.limit_minor}
                              currency={b.currency_code}
                            />
                          </small>
                          {b.valuation.incomplete && (
                            <small className="warning">
                              {t("fxIncomplete")}
                            </small>
                          )}
                        </Link>
                      );
                    })
                  ) : (
                    <Empty
                      title="emptyBudgets"
                      hint="emptyBudgetsHint"
                      action={
                        <Link to="/budgets">
                          {t("new")}
                          <ChevronRight size={16} />
                        </Link>
                      }
                    />
                  )}
                </section>
                <section className="panel">
                  <SectionHeading title={t("liabilities")} to="/liabilities" />
                  {d.liabilities.length ? (
                    d.liabilities.slice(0, 3).map((l) => (
                      <Link
                        className="summary-line debt-preview"
                        to="/liabilities"
                        key={l.id}
                      >
                        <span>
                          <strong>{l.counterparty_name}</strong>
                          <small>
                            {t(l.kind)}
                            {l.due_on ? ` · ${l.due_on}` : ""}
                          </small>
                        </span>
                        <MoneyAmount
                          amount={l.principal_minor}
                          currency={l.currency_code}
                        />
                      </Link>
                    ))
                  ) : (
                    <Empty title="emptyDebts" hint="emptyDebtsHint" />
                  )}
                </section>
              </div>
            </div>
            <details
              className="panel valuation-settings"
              open={valuationOpen}
              onToggle={(e) => setValuationOpen(e.currentTarget.open)}
            >
              <summary>{t("valuationSettings")}</summary>
              <div className="filters">
                <label>
                  <span>{t("fxDisplayCurrency")}</span>
                  <select
                    aria-label={t("fxDisplayCurrency")}
                    value={displayCurrency}
                    onChange={(e) =>
                      setDisplayCurrency(e.target.value as Currency)
                    }
                  >
                    {currencies.map((c) => (
                      <option key={c}>{c}</option>
                    ))}
                  </select>
                </label>
                <label>
                  <span>{t("fxValuationDate")}</span>
                  <input
                    type="date"
                    required
                    value={valuationDate}
                    onChange={(e) => {
                      if (e.target.value) setValuationDate(e.target.value);
                    }}
                  />
                </label>
              </div>
              <ValuationNote value={d.consolidated.balances} />
              <FxRefresh
                date={valuationDate}
                dates={Array.from(
                  new Set(
                    d.consolidated.cash_flow.missing.map((m) => m.requested_on),
                  ),
                )}
              />
              <div className="flow-grid">
                {order.map((c) => (
                  <div key={c}>
                    <h3>
                      {t("flow")} · {c}
                    </h3>
                    <dl className="metrics">
                      {(
                        ["income_minor", "expense_minor", "net_minor"] as const
                      ).map((key, i) => (
                        <div key={key}>
                          <dt>{t(["incomes", "expenses", "flow"][i])}</dt>
                          <dd>
                            <MoneyAmount
                              amount={d.cash_flow[c][key]}
                              currency={c}
                            />
                          </dd>
                        </div>
                      ))}
                    </dl>
                  </div>
                ))}
              </div>
            </details>
          </>
        )}
      </State>
    </>
  );
}
