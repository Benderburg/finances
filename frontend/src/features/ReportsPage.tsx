import { useContext, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Printer } from "lucide-react";
import { get } from "../data/api";
import type {
  Category,
  Currency,
  Flow,
  Session,
  Valuation,
} from "../domain/types";
import { currencies, formatMoney, today } from "../domain/money";
import { categoryName, LocaleContext, useT } from "../i18n";
import { Empty, State, useList } from "../components/ui";
import { FxRefresh, ValuationNote } from "./FxViews";

interface CashFlowReport {
  consolidated: Valuation;
  date_from: string;
  date_to: string;
  generated_at: string;
  currencies: Record<Currency, Flow>;
  months: {
    month: string;
    currencies: Record<Currency, Flow>;
    consolidated: Valuation;
  }[];
}
interface ExpensesReport {
  consolidated: Valuation;
  categories: {
    category_id: string;
    currency_code: Currency;
    amount_minor: string;
  }[];
}
interface BalanceReport {
  consolidated_points: { date: string; valuation: Valuation }[];
  reconstructed_history: boolean;
  accounts: {
    account_id: string;
    name: string;
    currency_code: Currency;
    points: { date: string; balance_minor: string }[];
  }[];
}
const scaled = (amount: string, max: bigint) =>
  max === 0n ? 0 : Number((BigInt(amount) * 10000n) / max) / 100;
export function ReportsPage({ session }: { session: Session }) {
  const t = useT(),
    locale = useContext(LocaleContext);
  const [month, setMonth] = useState(
      today(session.settings.timezone).slice(0, 7),
    ),
    [currency, setCurrency] = useState<Currency>(
      session.settings.base_currency_code,
    ),
    [goalOnly, setGoalOnly] = useState(false),
    [excludeSettlements, setExclude] = useState(false);
  const flow = useQuery({
      queryKey: ["report-flow", month, currency],
      queryFn: () =>
        get<CashFlowReport>(
          `/reports/cash-flow?month=${month}&display_currency=${currency}`,
        ),
    }),
    expenses = useQuery({
      queryKey: [
        "report-expenses",
        month,
        currency,
        goalOnly,
        excludeSettlements,
      ],
      queryFn: () =>
        get<ExpensesReport>(
          `/reports/expenses?month=${month}&display_currency=${currency}${goalOnly ? "&goal_only=1" : ""}${excludeSettlements ? "&exclude_settlements=1" : ""}`,
        ),
    }),
    balances = useQuery({
      queryKey: ["report-balances", month, currency],
      queryFn: () =>
        get<BalanceReport>(
          `/reports/balances?month=${month}&display_currency=${currency}`,
        ),
    }),
    cats = useList<Category>("/categories?per_page=100");
  return (
    <>
      <div className="page-heading">
        <h1>{t("reports")}</h1>
        <button onClick={() => window.print()}>
          <Printer size={18} />
          {t("print")}
        </button>
      </div>
      <FxRefresh
        date={today(session.settings.timezone)}
        dates={
          flow.data?.data.consolidated.missing
            .map((m) => m.requested_on)
            .filter((d) => d.startsWith(month)) ?? []
        }
      />
      <div className="filters print-hide">
        <label>
          <span>{t("month")}</span>
          <input
            type="month"
            value={month}
            onChange={(e) => setMonth(e.target.value)}
          />
        </label>
        <label>
          <span>{t("currency")}</span>
          <select
            aria-label={t("currency")}
            value={currency}
            onChange={(e) => setCurrency(e.target.value as Currency)}
          >
            {currencies.map((c) => (
              <option key={c}>{c}</option>
            ))}
          </select>
        </label>
        <label className="check">
          <input
            type="checkbox"
            checked={goalOnly}
            onChange={(e) => setGoalOnly(e.target.checked)}
          />
          {t("goal_expense")}
        </label>
        <label className="check">
          <input
            type="checkbox"
            checked={excludeSettlements}
            onChange={(e) => setExclude(e.target.checked)}
          />
          {t("settlement")} −
        </label>
      </div>
      <section className="panel">
        <h2>
          {t("flow")} · {currency}
        </h2>
        <State query={flow}>
          {(d) => {
            const max = d.months.reduce((m, row) => {
              const f = row.consolidated.known_subtotal;
              return [BigInt(f.income_minor), BigInt(f.expense_minor)].reduce(
                (a, b) => (a > b ? a : b),
                m,
              );
            }, 1n);
            return (
              <>
                <ValuationNote value={d.consolidated} />
                <p className="subtle">
                  {d.date_from} — {d.date_to} · {d.generated_at}
                </p>
                <div className="chart-bars">
                  {d.months.map((row) => (
                    <div className="chart-month" key={row.month}>
                      <div className="bar-pair">
                        <div
                          className="bar income-bar"
                          style={{
                            height:
                              scaled(
                                row.consolidated.known_subtotal.income_minor,
                                max,
                              ) + "%",
                          }}
                          title={formatMoney(
                            row.consolidated.known_subtotal.income_minor,
                            currency,
                            locale,
                          )}
                        />
                        <div
                          className="bar expense-bar"
                          style={{
                            height:
                              scaled(
                                row.consolidated.known_subtotal.expense_minor,
                                max,
                              ) + "%",
                          }}
                          title={formatMoney(
                            row.consolidated.known_subtotal.expense_minor,
                            currency,
                            locale,
                          )}
                        />
                      </div>
                      <small>{row.month}</small>
                    </div>
                  ))}
                </div>
                <div className="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th>{t("month")}</th>
                        <th>{t("incomes")}</th>
                        <th>{t("expenses")}</th>
                        <th>{t("flow")}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {d.months.map((row) => (
                        <tr key={row.month}>
                          <td>
                            {row.month}
                            {row.consolidated.incomplete
                              ? ` · ${t("fxIncomplete")}`
                              : ""}
                          </td>
                          <td>
                            {formatMoney(
                              row.consolidated.known_subtotal.income_minor,
                              currency,
                              locale,
                            )}
                          </td>
                          <td>
                            {formatMoney(
                              row.consolidated.known_subtotal.expense_minor,
                              currency,
                              locale,
                            )}
                          </td>
                          <td>
                            {formatMoney(
                              row.consolidated.known_subtotal.net_minor,
                              currency,
                              locale,
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </>
            );
          }}
        </State>
      </section>
      <section className="panel">
        <h2>
          {t("expenses")} · {currency}
        </h2>
        <State query={expenses}>
          {(d) => {
            const converted = Object.entries(d.consolidated.known_subtotal).map(
              ([category_id, amount_minor]) => ({
                category_id,
                amount_minor,
                currency_code: currency,
              }),
            );
            const max = converted.reduce(
              (m, c) =>
                BigInt(c.amount_minor) > m ? BigInt(c.amount_minor) : m,
              1n,
            );
            return (
              <>
                <ValuationNote value={d.consolidated} />
                {converted.length ? (
                  <div className="expense-structure">
                    {converted.map((c) => {
                      const cat = cats.data?.data.items.find(
                        (x) => x.id === c.category_id,
                      );
                      return (
                        <div key={c.category_id}>
                          <div className="summary-line">
                            <span>
                              {cat ? categoryName(cat, t) : t("category")}
                            </span>
                            <strong>
                              {formatMoney(
                                c.amount_minor,
                                c.currency_code,
                                locale,
                              )}
                            </strong>
                          </div>
                          <div className="progress">
                            <span
                              style={{
                                width: scaled(c.amount_minor, max) + "%",
                              }}
                            />
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <Empty />
                )}
              </>
            );
          }}
        </State>
      </section>
      <section className="panel">
        <h2>
          {t("balanceHistory")} · {currency}
        </h2>
        <p className="subtle">{t("reconstructed")}</p>
        <State query={balances}>
          {(d) => (
            <>
              <details>
                <summary>
                  {t("fxTotalBalance")} · {currency}
                </summary>
                <div className="table-wrap">
                  <table>
                    <tbody>
                      {d.consolidated_points.map((p) => (
                        <tr key={p.date}>
                          <td>{p.date}</td>
                          <td>
                            {formatMoney(
                              p.valuation.known_subtotal.amount_minor ?? "0",
                              currency,
                              locale,
                            )}
                          </td>
                          <td>
                            {p.valuation.incomplete && (
                              <ValuationNote value={p.valuation} />
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </details>
              {d.accounts.length ? (
                d.accounts.map((a) => {
                  let min = 0n,
                    max = 1n;
                  for (const p of a.points) {
                    const v = BigInt(p.balance_minor);
                    if (v < min) min = v;
                    if (v > max) max = v;
                  }
                  const range = max - min;
                  const points = a.points
                    .map(
                      (p, i) =>
                        `${(i * 100) / Math.max(1, a.points.length - 1)},${100 - scaled((BigInt(p.balance_minor) - min).toString(), range)}`,
                    )
                    .join(" ");
                  return (
                    <div className="balance-chart" key={a.account_id}>
                      <h3>{a.name}</h3>
                      <svg
                        viewBox="0 0 100 100"
                        preserveAspectRatio="none"
                        role="img"
                        aria-label={t("balanceHistory") + " " + a.name}
                      >
                        <line
                          x1="0"
                          x2="100"
                          y1={100 - scaled((-min).toString(), range)}
                          y2={100 - scaled((-min).toString(), range)}
                          stroke="var(--border)"
                          strokeWidth=".5"
                        />
                        <polyline
                          points={points}
                          fill="none"
                          stroke="var(--accent)"
                          strokeWidth="1"
                          vectorEffect="non-scaling-stroke"
                        />
                      </svg>
                      <div className="summary-line">
                        <span>{a.points[0]?.date}</span>
                        <strong>
                          {a.points.length
                            ? formatMoney(
                                a.points[a.points.length - 1].balance_minor,
                                a.currency_code,
                                locale,
                              )
                            : ""}
                        </strong>
                        <span>{a.points[a.points.length - 1]?.date}</span>
                      </div>
                      <details>
                        <summary>{t("details")}</summary>
                        <div className="table-wrap">
                          <table>
                            <tbody>
                              {a.points.map((p) => (
                                <tr key={p.date}>
                                  <td>{p.date}</td>
                                  <td>
                                    {formatMoney(
                                      p.balance_minor,
                                      a.currency_code,
                                      locale,
                                    )}
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </details>
                    </div>
                  );
                })
              ) : (
                <Empty />
              )}
            </>
          )}
        </State>
      </section>
    </>
  );
}
