import { useContext, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Link, useSearchParams } from "react-router-dom";
import {
  Plus,
  ArrowDownLeft,
  ArrowUpRight,
  ArrowLeftRight,
  Wallet,
  Target,
} from "lucide-react";
import type {
  Account,
  Budget,
  BudgetTemplate,
  Category,
  Dashboard,
  Goal,
  Liability,
  Operation,
  Session,
} from "../domain/types";
import { currencies, formatMoney, inputMoney, today } from "../domain/money";
import { get } from "../data/api";
import { categoryName, LocaleContext, useT } from "../i18n";
import {
  Editor,
  Empty,
  ErrorMessage,
  Modal,
  Pager,
  Progress,
  State,
  moneyField,
  useCommand,
  useAllList,
  useList,
  type Field,
} from "../components/ui";
import { OperationForm } from "./OperationForm";

export function OperationRow({
  o,
  accounts = [],
  categories = [],
  onClick,
}: {
  o: Operation;
  accounts?: Account[];
  categories?: Category[];
  onClick: () => void;
}) {
  const t = useT(),
    locale = useContext(LocaleContext);
  const pair = o.type === "transfer" || o.type === "exchange";
  const name = (id: string | null) =>
    accounts.find((a) => a.id === id)?.name ?? "…";
  const c = categories.find((c) => c.id === o.category_id);
  return (
    <button
      className={`operation-row ${o.status === "voided" ? "voided" : ""}`}
      onClick={onClick}
    >
      <span className={`operation-icon ${o.type}`}>
        {pair ? (
          <ArrowLeftRight size={19} />
        ) : o.type === "income" ? (
          <ArrowDownLeft size={19} />
        ) : (
          <ArrowUpRight size={19} />
        )}
      </span>
      <span className="operation-text">
        <strong>
          {pair
            ? `${name(o.from_account_id)} → ${name(o.to_account_id)}`
            : o.description || (c ? categoryName(c, t) : t(o.type))}
        </strong>
        <small>
          {o.occurred_on} · {t(o.type)}
          {o.liability_id ? " · " + t("settlement") : ""}
          {o.goal_id ? " · " + t("goals") : ""}
          {o.status === "voided" ? " · " + t("voided") : ""}
        </small>
      </span>
      <span className={`operation-amount ${o.type}`}>
        {o.type === "expense" ? "−" : o.type === "income" ? "+" : ""}
        {formatMoney(o.amount_minor, o.currency_code, locale)}
        {pair && o.target_amount_minor && o.target_currency_code && (
          <small>
            →{" "}
            {formatMoney(o.target_amount_minor, o.target_currency_code, locale)}
          </small>
        )}
      </span>
    </button>
  );
}
export function DashboardPage({
  session,
  openOperation,
}: {
  session: Session;
  openOperation: (o?: Operation) => void;
}) {
  const t = useT(),
    locale = useContext(LocaleContext);
  const [month, setMonth] = useState(
    today(session.settings.timezone).slice(0, 7),
  );
  const q = useQuery({
    queryKey: ["dashboard", month],
    queryFn: () => get<Dashboard>("/dashboard?month=" + month),
  });
  const ac = useAllList<Account>("/accounts");
  const cats = useAllList<Category>("/categories");
  const order = [
    session.settings.base_currency_code,
    ...currencies.filter((c) => c !== session.settings.base_currency_code),
  ];
  return (
    <>
      <div className="page-heading">
        <div>
          <p className="eyebrow">
            {t("hello")}, {session.user.full_name}
          </p>
          <h1>{t("overview")}</h1>
        </div>
        <input
          aria-label={t("month")}
          type="month"
          value={month}
          onChange={(e) => setMonth(e.target.value)}
        />
      </div>
      <State query={q}>
        {(d) => (
          <>
            <p className="subtle">{t("current")}</p>
            <div className="balance-grid">
              {order.map((c) => (
                <section
                  className={`balance-card ${c === session.settings.base_currency_code ? "lead" : ""}`}
                  key={c}
                >
                  <div className="card-top">
                    <span>{t("total")}</span>
                    <span className="currency-tag">{c}</span>
                  </div>
                  <h2>{formatMoney(d.balances[c].total_minor, c, locale)}</h2>
                  <div className="balance-split">
                    <span>
                      {t("available")}
                      <strong>
                        {formatMoney(d.balances[c].available_minor, c, locale)}
                      </strong>
                    </span>
                    <span>
                      {t("savings")}
                      <strong>
                        {formatMoney(d.balances[c].savings_minor, c, locale)}
                      </strong>
                    </span>
                  </div>
                </section>
              ))}
            </div>
            <p className="subtle">{t("neutralHint")}</p>
            <div className="flow-grid">
              {order.map((c) => (
                <section className="panel" key={c}>
                  <div className="card-top">
                    <h3>{t("flow")}</h3>
                    <span>
                      {c} · {month}
                    </span>
                  </div>
                  <dl className="metrics">
                    <div>
                      <dt>{t("incomes")}</dt>
                      <dd className="income">
                        {formatMoney(d.cash_flow[c].income_minor, c, locale)}
                      </dd>
                    </div>
                    <div>
                      <dt>{t("expenses")}</dt>
                      <dd>
                        {formatMoney(d.cash_flow[c].expense_minor, c, locale)}
                      </dd>
                    </div>
                    <div>
                      <dt>{t("flow")}</dt>
                      <dd>
                        {formatMoney(d.cash_flow[c].net_minor, c, locale)}
                      </dd>
                    </div>
                  </dl>
                </section>
              ))}
            </div>
            <div className="dashboard-lower">
              <section className="panel">
                <div className="card-top">
                  <h2>{t("recent")}</h2>
                  <Link to="/operations">{t("viewAll")}</Link>
                </div>
                {d.recent_operations.length ? (
                  d.recent_operations.map((o) => (
                    <OperationRow
                      o={o}
                      accounts={ac.data?.data.items}
                      categories={cats.data?.data.items}
                      onClick={() => openOperation(o)}
                      key={o.id}
                    />
                  ))
                ) : (
                  <Empty
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
                <div className="card-top">
                  <h2>{t("goals")}</h2>
                  <Link to="/goals">{t("viewAll")}</Link>
                </div>
                {d.goals.length ? (
                  d.goals.map((g) => (
                    <Link className="mini-goal" to="/goals" key={g.id}>
                      <span className="goal-icon">{g.icon ?? "🎯"}</span>
                      <div>
                        <strong>{g.name}</strong>
                        <small>
                          {formatMoney(
                            g.funded_lifetime_minor,
                            g.currency_code,
                            locale,
                          )}{" "}
                          /{" "}
                          {formatMoney(
                            g.target_amount_minor,
                            g.currency_code,
                            locale,
                          )}
                        </small>
                        <Progress value={g.progress} />
                      </div>
                      <span>{g.progress}%</span>
                    </Link>
                  ))
                ) : (
                  <Empty />
                )}
              </section>
            </div>
            <div className="flow-grid">
              <section className="panel">
                <h2>{t("budgets")}</h2>
                {d.budgets.map((b) => (
                  <Link to="/budgets" className="summary-line" key={b.id}>
                    <span>
                      {cats.data?.data.items.find((c) => c.id === b.category_id)
                        ?.name ??
                        t(
                          cats.data?.data.items.find(
                            (c) => c.id === b.category_id,
                          )?.system_code ?? "category",
                        )}
                    </span>
                    <strong>
                      {formatMoney(b.fact_minor, b.currency_code, locale)} /{" "}
                      {formatMoney(b.limit_minor, b.currency_code, locale)}
                    </strong>
                  </Link>
                ))}
              </section>
              <section className="panel">
                <h2>{t("liabilities")}</h2>
                {d.liabilities.map((l) => (
                  <Link to="/liabilities" className="summary-line" key={l.id}>
                    <span>
                      {l.counterparty_name} · {t(l.kind)}
                    </span>
                    <strong>
                      {formatMoney(l.principal_minor, l.currency_code, locale)}
                    </strong>
                  </Link>
                ))}
              </section>
            </div>
          </>
        )}
      </State>
    </>
  );
}
export function OperationsPage({
  openOperation,
}: {
  openOperation: (o?: Operation) => void;
}) {
  const t = useT();
  const [params, setParams] = useSearchParams();
  const p = Number(params.get("page") ?? 1);
  const q = useList<Operation>("/operations?" + params.toString());
  const ac = useAllList<Account>("/accounts");
  const cats = useAllList<Category>("/categories");
  const filter = (key: string, value: string) => {
    const next = new URLSearchParams(params);
    value ? next.set(key, value) : next.delete(key);
    next.set("page", "1");
    setParams(next);
  };
  return (
    <>
      <div className="page-heading">
        <h1>{t("operations")}</h1>
        <button className="primary" onClick={() => openOperation()}>
          <Plus size={18} />
          {t("new")}
        </button>
      </div>
      <div className="filters">
        <label>
          <span>{t("kind")}</span>
          <select
            value={params.get("type") ?? ""}
            onChange={(e) => filter("type", e.target.value)}
          >
            <option value="">{t("all")}</option>
            {["income", "expense", "transfer", "exchange"].map((k) => (
              <option key={k} value={k}>
                {t(k)}
              </option>
            ))}
          </select>
        </label>
        <label>
          <span>{t("account")}</span>
          <select
            value={params.get("account_id") ?? ""}
            onChange={(e) => filter("account_id", e.target.value)}
          >
            <option value="">{t("all")}</option>
            {ac.data?.data.items.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          <span>{t("category")}</span>
          <select
            value={params.get("category_id") ?? ""}
            onChange={(e) => filter("category_id", e.target.value)}
          >
            <option value="">{t("all")}</option>
            {cats.data?.data.items.map((c) => (
              <option key={c.id} value={c.id}>
                {categoryName(c, t)}
              </option>
            ))}
          </select>
        </label>
        <label>
          <span>{t("posted")}</span>
          <select
            value={params.get("status") ?? ""}
            onChange={(e) => filter("status", e.target.value)}
          >
            <option value="">{t("all")}</option>
            {["posted", "voided"].map((k) => (
              <option key={k} value={k}>
                {t(k)}
              </option>
            ))}
          </select>
        </label>
        <label>
          <span>{t("fromDate")}</span>
          <input
            type="date"
            value={params.get("date_from") ?? ""}
            onChange={(e) => filter("date_from", e.target.value)}
          />
        </label>
        <label>
          <span>{t("toDate")}</span>
          <input
            type="date"
            value={params.get("date_to") ?? ""}
            onChange={(e) => filter("date_to", e.target.value)}
          />
        </label>
        <label className="search-field">
          <span>{t("search")}</span>
          <input
            value={params.get("q") ?? ""}
            onChange={(e) => filter("q", e.target.value)}
          />
        </label>
      </div>
      <section className="panel">
        <State query={q}>
          {(d) => (
            <>
              {d.items.length ? (
                d.items.map((o) => (
                  <OperationRow
                    o={o}
                    accounts={ac.data?.data.items}
                    categories={cats.data?.data.items}
                    onClick={() => openOperation(o)}
                    key={o.id}
                  />
                ))
              ) : (
                <Empty />
              )}
              <Pager
                page={p}
                pages={d.pagination.pages}
                onPage={(page) =>
                  setParams({
                    ...Object.fromEntries(params),
                    page: String(page),
                  })
                }
              />
            </>
          )}
        </State>
      </section>
    </>
  );
}
export function OperationDetails({
  o,
  session,
  onClose,
  onEdit,
}: {
  o: Operation;
  session: Session;
  onClose: () => void;
  onEdit: (o: Operation) => void;
}) {
  const t = useT(),
    locale = useContext(LocaleContext),
    mutation = useCommand();
  const q = useQuery({
    queryKey: ["operation", o.id],
    queryFn: () => get<Operation>("/operations/" + o.id),
  });
  const accounts = useAllList<Account>("/accounts");
  const cats = useAllList<Category>("/categories");
  const name = (id: string | null) =>
    accounts.data?.data.items.find((a) => a.id === id)?.name ?? id;
  return (
    <Modal title={t("details")} onClose={onClose}>
      <State query={q}>
        {(d) => (
          <>
            <dl className="details-list">
              <div>
                <dt>{t("kind")}</dt>
                <dd>
                  {t(d.type)} · {t(d.status)}
                </dd>
              </div>
              <div>
                <dt>{t("date")}</dt>
                <dd>{d.occurred_on}</dd>
              </div>
              <div>
                <dt>{t("amount")}</dt>
                <dd>{formatMoney(d.amount_minor, d.currency_code, locale)}</dd>
              </div>
              {d.account_id ? (
                <div>
                  <dt>{t("account")}</dt>
                  <dd>{name(d.account_id)}</dd>
                </div>
              ) : (
                <>
                  <div>
                    <dt>{t("source")}</dt>
                    <dd>{name(d.from_account_id)}</dd>
                  </div>
                  <div>
                    <dt>{t("destination")}</dt>
                    <dd>{name(d.to_account_id)}</dd>
                  </div>
                  {d.target_amount_minor && d.target_currency_code && (
                    <div>
                      <dt>{t("received")}</dt>
                      <dd>
                        {formatMoney(
                          d.target_amount_minor,
                          d.target_currency_code,
                          locale,
                        )}
                      </dd>
                    </div>
                  )}
                  <div>
                    <dt>{t("rate")}</dt>
                    <dd>
                      {d.quoted_rate && <span>{d.quoted_rate} → </span>}
                      {d.effective_rate}
                    </dd>
                  </div>
                </>
              )}
              {d.category_id && (
                <div>
                  <dt>{t("category")}</dt>
                  <dd>
                    {cats.data?.data.items.find(
                      (c) => c.id === d.category_id,
                    ) &&
                      categoryName(
                        cats.data.data.items.find(
                          (c) => c.id === d.category_id,
                        )!,
                        t,
                      )}
                  </dd>
                </div>
              )}
              <div>
                <dt>{t("description")}</dt>
                <dd>{d.description || "—"}</dd>
              </div>
              {d.liability_id && (
                <div>
                  <dt>{t("settlement")}</dt>
                  <dd>
                    <Link onClick={onClose} to="/liabilities">
                      {t("liabilities")}
                    </Link>
                  </dd>
                </div>
              )}
              {d.goal_id && (
                <div>
                  <dt>{t("goals")}</dt>
                  <dd>
                    <Link onClick={onClose} to="/goals">
                      {t("goals")}
                    </Link>
                    {d.goal_completion_requested ? " · " + t("complete") : ""}
                  </dd>
                </div>
              )}
            </dl>
            <details className="audit-history">
              <summary>
                {t("history")} · {d.history?.length ?? 0}
              </summary>
              {(d.history ?? []).map((entry, i) => {
                const h = entry as {
                  action: string;
                  created_at: string;
                  before_payload: Operation | null;
                  after_payload: Operation;
                };
                return (
                  <section className="panel" key={i}>
                    <strong>
                      {t(h.action)} · {h.created_at}
                    </strong>
                    {(
                      [
                        ["before", h.before_payload],
                        ["after", h.after_payload],
                      ] as const
                    ).map(
                      ([label, value]) =>
                        value && (
                          <p key={label}>
                            {t(label)}: {value.occurred_on} ·{" "}
                            {formatMoney(
                              value.amount_minor,
                              value.currency_code,
                              locale,
                            )}
                            {value.target_amount_minor &&
                            value.target_currency_code
                              ? " → " +
                                formatMoney(
                                  value.target_amount_minor,
                                  value.target_currency_code,
                                  locale,
                                )
                              : ""}{" "}
                            · {value.description || "—"}
                          </p>
                        ),
                    )}
                  </section>
                );
              })}
            </details>
            <ErrorMessage error={mutation.error} />
            {d.status === "posted" && !d.liability_id && (
              <footer className="form-actions">
                <button onClick={() => onEdit(d)}>{t("edit")}</button>
                <button
                  className="danger"
                  disabled={mutation.isPending}
                  onClick={() => {
                    if (
                      window.confirm(
                        `${t("void")}? ${name(d.account_id ?? d.from_account_id)}${d.to_account_id ? " → " + name(d.to_account_id) : ""} · ${formatMoney(d.amount_minor, d.currency_code, locale)}`,
                      )
                    )
                      void mutation
                        .submit("/operations/" + d.id + "/void", "POST", {
                          expected_revision: d.revision,
                        })
                        .then(onClose)
                        .catch(() => {});
                  }}
                >
                  {t("void")}
                </button>
              </footer>
            )}
          </>
        )}
      </State>
    </Modal>
  );
}
export function AccountsPage({
  session,
  openOperation,
}: {
  session: Session;
  openOperation: (o?: Operation, to?: string, type?: Operation["type"]) => void;
}) {
  const t = useT(),
    locale = useContext(LocaleContext),
    mutation = useCommand();
  const [page, setPage] = useState(1),
    [kind, setKind] = useState(""),
    [archive, setArchive] = useState("all"),
    [editor, setEditor] = useState<Account | "new" | null>(null);
  const q = useList<Account>(
    `/accounts?page=${page}&archive=${archive}${kind ? "&kind=" + kind : ""}`,
  );
  const fields = (a?: Account): Field[] => [
    { key: "name", label: t("name"), required: true, initial: a?.name },
    {
      key: "kind",
      label: t("kind"),
      type: "select",
      required: true,
      initial: a?.kind ?? "regular",
      options: [
        { value: "regular", label: t("regular") },
        { value: "savings", label: t("savingsKind") },
      ],
    },
    ...(!a
      ? [
          {
            key: "currency_code",
            label: t("currency"),
            type: "select",
            required: true,
            initial: session.settings.base_currency_code,
            options: currencies.map((c) => ({ value: c, label: c })),
          } as Field,
          moneyField("opening_balance_minor", t("opening"), "0", true),
        ]
      : []),
    {
      key: "include_in_total",
      label: t("included"),
      type: "checkbox",
      initial: a?.include_in_total ?? true,
    },
  ];
  return (
    <>
      <div className="page-heading">
        <h1>{t("accounts")}</h1>
        <button className="primary" onClick={() => setEditor("new")}>
          <Plus size={18} />
          {t("new")}
        </button>
      </div>
      <div className="filters">
        <select
          aria-label={t("kind")}
          value={kind}
          onChange={(e) => {
            setKind(e.target.value);
            setPage(1);
          }}
        >
          <option value="">{t("all")}</option>
          <option value="regular">{t("regular")}</option>
          <option value="savings">{t("savingsKind")}</option>
        </select>
        <select
          aria-label={t("archive")}
          value={archive}
          onChange={(e) => {
            setArchive(e.target.value);
            setPage(1);
          }}
        >
          <option value="all">{t("all")}</option>
          <option value="active">{t("posted")}</option>
          <option value="archived">{t("archived")}</option>
        </select>
      </div>
      <ErrorMessage error={mutation.error} />
      <State query={q}>
        {(d) => (
          <>
            {d.items.length ? (
              <div className="entity-grid">
                {d.items.map((a) => (
                  <section className="entity-card" key={a.id}>
                    <div className="card-top">
                      <span className="entity-icon">
                        <Wallet size={22} />
                      </span>
                      <span className="badge">
                        {a.currency_code} ·{" "}
                        {t(
                          a.archived_at
                            ? "archived"
                            : a.kind === "savings"
                              ? "savingsKind"
                              : "regular",
                        )}
                      </span>
                    </div>
                    <h2>{a.name}</h2>
                    <p className="large-money">
                      {formatMoney(a.balance_minor, a.currency_code, locale)}
                    </p>
                    <small>{a.include_in_total ? t("included") : ""}</small>
                    {a.goal_id && <Link to="/goals">{t("goals")}</Link>}
                    <div className="card-actions">
                      <Link to={"/operations?account_id=" + a.id}>
                        {t("balanceHistory")}
                      </Link>
                      <button onClick={() => setEditor(a)}>{t("edit")}</button>
                      <button
                        disabled={mutation.isPending}
                        onClick={() =>
                          void mutation
                            .submit(
                              `/accounts/${a.id}/${a.archived_at ? "unarchive" : "archive"}`,
                              "POST",
                              { expected_revision: a.revision },
                            )
                            .catch(() => {})
                        }
                      >
                        {t(a.archived_at ? "unarchive" : "archive")}
                      </button>
                      {!a.archived_at && (
                        <button
                          onClick={() =>
                            openOperation(undefined, a.id, "transfer")
                          }
                        >
                          {t("transfer")}
                        </button>
                      )}
                      {a.opening_balance_minor === "0" &&
                        a.balance_minor === "0" &&
                        !a.goal_id && (
                          <button
                            onClick={() => {
                              if (confirm(t("delete") + " " + a.name + "?"))
                                void mutation
                                  .submit("/accounts/" + a.id, "DELETE", {
                                    expected_revision: a.revision,
                                  })
                                  .catch(() => {});
                            }}
                          >
                            {t("delete")}
                          </button>
                        )}
                    </div>
                  </section>
                ))}
              </div>
            ) : (
              <Empty />
            )}
            <Pager page={page} pages={d.pagination.pages} onPage={setPage} />
          </>
        )}
      </State>
      {editor && (
        <Editor
          title={t(editor === "new" ? "new" : "edit") + " " + t("account")}
          path={"/accounts" + (editor === "new" ? "" : "/" + editor.id)}
          method={editor === "new" ? "POST" : "PATCH"}
          revision={editor === "new" ? undefined : editor.revision}
          fields={fields(editor === "new" ? undefined : editor)}
          onClose={() => setEditor(null)}
        />
      )}
    </>
  );
}
export function GoalsPage({ session }: { session: Session }) {
  const t = useT(),
    locale = useContext(LocaleContext),
    mutation = useCommand();
  const [page, setPage] = useState(1),
    [editor, setEditor] = useState<Goal | "new" | null>(null),
    [spend, setSpend] = useState<Goal | null>(null),
    [topUp, setTopUp] = useState<string | null>(null);
  const q = useList<Goal>("/goals?page=" + page),
    accounts = useAllList<Account>("/accounts?kind=savings&archive=active");
  const fields = (g?: Goal): Field[] => [
    { key: "name", label: t("name"), required: true, initial: g?.name },
    moneyField("target_amount_minor", t("target"), g?.target_amount_minor),
    ...(!g
      ? [
          {
            key: "currency_code",
            label: t("currency"),
            type: "select",
            required: true,
            initial: session.settings.base_currency_code,
            options: currencies.map((c) => ({ value: c, label: c })),
          } as Field,
          {
            key: "savings_account_id",
            label: t("account"),
            type: "select",
            nullable: true,
            options: accounts.data?.data.items
              .filter((a) => !a.goal_id)
              .map((a) => ({
                value: a.id,
                label: a.name + " · " + a.currency_code,
              })),
          } as Field,
        ]
      : []),
    {
      key: "deadline",
      label: t("deadline"),
      type: "date",
      nullable: true,
      initial: g?.deadline ?? "",
    },
    { key: "icon", label: "✦", initial: g?.icon ?? "🎯", maxLength: 32 },
  ];
  return (
    <>
      <div className="page-heading">
        <h1>{t("goals")}</h1>
        <button className="primary" onClick={() => setEditor("new")}>
          <Plus size={18} />
          {t("new")}
        </button>
      </div>
      <ErrorMessage error={mutation.error} />
      <State query={q}>
        {(d) => (
          <>
            {d.items.length ? (
              <div className="entity-grid">
                {d.items.map((g) => (
                  <section className="entity-card" key={g.id}>
                    <div className="card-top">
                      <span className="goal-icon">{g.icon ?? <Target />}</span>
                      <span className="badge">{t(g.status)}</span>
                    </div>
                    <h2>{g.name}</h2>
                    <p className="large-money">
                      {formatMoney(
                        g.target_amount_minor,
                        g.currency_code,
                        locale,
                      )}
                    </p>
                    <Progress value={g.progress} />
                    <dl className="metrics">
                      <div>
                        <dt>{t("savedNow")}</dt>
                        <dd>
                          {formatMoney(
                            g.saved_now_minor,
                            g.currency_code,
                            locale,
                          )}
                        </dd>
                      </div>
                      <div>
                        <dt>{t("spentGoal")}</dt>
                        <dd>
                          {formatMoney(
                            g.spent_on_goal_minor,
                            g.currency_code,
                            locale,
                          )}
                        </dd>
                      </div>
                      <div>
                        <dt>{t("funded")}</dt>
                        <dd>
                          {formatMoney(
                            g.funded_lifetime_minor,
                            g.currency_code,
                            locale,
                          )}
                        </dd>
                      </div>
                    </dl>
                    {g.deadline && (
                      <small>
                        {t("deadline")}: {g.deadline}
                      </small>
                    )}
                    {g.legacy_read_only ? (
                      <p className="warning">{t("legacy")}</p>
                    ) : (
                      <div className="card-actions">
                        <button onClick={() => setTopUp(g.savings_account_id)}>
                          {t("topUp")}
                        </button>
                        {g.status !== "cancelled" && (
                          <button onClick={() => setSpend(g)}>
                            {t("spend")}
                          </button>
                        )}
                        <button onClick={() => setEditor(g)}>
                          {t("edit")}
                        </button>
                        <button
                          onClick={() =>
                            void mutation
                              .submit(
                                `/goals/${g.id}/${g.status === "cancelled" ? "resume" : "cancel"}`,
                                "POST",
                                { expected_revision: g.revision },
                              )
                              .catch(() => {})
                          }
                        >
                          {t(
                            g.status === "cancelled"
                              ? "resume"
                              : "cancelObject",
                          )}
                        </button>
                        {g.funded_lifetime_minor === "0" && (
                          <button
                            onClick={() => {
                              if (confirm(t("delete") + " " + g.name + "?"))
                                void mutation
                                  .submit("/goals/" + g.id, "DELETE", {
                                    expected_revision: g.revision,
                                  })
                                  .catch(() => {});
                            }}
                          >
                            {t("delete")}
                          </button>
                        )}
                      </div>
                    )}
                  </section>
                ))}
              </div>
            ) : (
              <Empty />
            )}
            <Pager page={page} pages={d.pagination.pages} onPage={setPage} />
          </>
        )}
      </State>
      {editor && (
        <Editor
          title={t(editor === "new" ? "new" : "edit") + " " + t("goals")}
          path={"/goals" + (editor === "new" ? "" : "/" + editor.id)}
          method={editor === "new" ? "POST" : "PATCH"}
          revision={editor === "new" ? undefined : editor.revision}
          fields={fields(editor === "new" ? undefined : editor)}
          onClose={() => setEditor(null)}
        />
      )}{" "}
      {spend && (
        <OperationForm
          session={session}
          goal={spend}
          onClose={() => setSpend(null)}
        />
      )}{" "}
      {topUp && (
        <OperationForm
          session={session}
          toAccount={topUp}
          onClose={() => setTopUp(null)}
        />
      )}
    </>
  );
}
export function LiabilitiesPage({ session }: { session: Session }) {
  const t = useT(),
    locale = useContext(LocaleContext),
    mutation = useCommand();
  const [page, setPage] = useState(1),
    [editor, setEditor] = useState<Liability | "new" | null>(null),
    [settle, setSettle] = useState<Liability | null>(null);
  const q = useList<Liability>("/liabilities?page=" + page);
  const fields = (l?: Liability): Field[] => [
    {
      key: "counterparty_name",
      label: t("counterparty"),
      required: true,
      initial: l?.counterparty_name,
    },
    ...(l?.status === "settled"
      ? []
      : [
          {
            key: "kind",
            label: t("kind"),
            type: "select",
            required: true,
            initial: l?.kind ?? "payable",
            options: ["receivable", "payable", "credit"].map((k) => ({
              value: k,
              label: t(k),
            })),
          } as Field,
          moneyField("principal_minor", t("amount"), l?.principal_minor),
          {
            key: "currency_code",
            label: t("currency"),
            type: "select",
            required: true,
            initial: l?.currency_code ?? session.settings.base_currency_code,
            options: currencies.map((c) => ({ value: c, label: c })),
          } as Field,
        ]),
    {
      key: "due_on",
      label: t("deadline"),
      type: "date",
      nullable: true,
      initial: l?.due_on ?? "",
    },
    {
      key: "comment",
      label: t("comment"),
      type: "textarea",
      initial: l?.comment ?? "",
    },
  ];
  return (
    <>
      <div className="page-heading">
        <h1>{t("liabilities")}</h1>
        <button className="primary" onClick={() => setEditor("new")}>
          <Plus size={18} />
          {t("new")}
        </button>
      </div>
      <p className="subtle">{t("debtHint")}</p>
      <ErrorMessage error={mutation.error} />
      <State query={q}>
        {(d) => (
          <>
            {d.totals && (
              <section className="panel">
                <h2>{t("openTotals")}</h2>
                <div className="flow-grid">
                  {currencies.map((c) => (
                    <div key={c}>
                      <h3>{c}</h3>
                      <dl className="metrics">
                        {(["receivable", "payable", "credit"] as const).map(
                          (kind) => (
                            <div key={kind}>
                              <dt>{t(kind)}</dt>
                              <dd>
                                {formatMoney(
                                  d.totals![c][
                                    (kind + "_minor") as
                                      | "receivable_minor"
                                      | "payable_minor"
                                      | "credit_minor"
                                  ],
                                  c,
                                  locale,
                                )}
                              </dd>
                            </div>
                          ),
                        )}
                      </dl>
                    </div>
                  ))}
                </div>
              </section>
            )}
            {d.items.length ? (
              <div className="entity-grid">
                {d.items.map((l) => (
                  <section className="entity-card" key={l.id}>
                    <div className="card-top">
                      <span>{t(l.kind)}</span>
                      <span className="badge">{t(l.status)}</span>
                    </div>
                    <h2>{l.counterparty_name}</h2>
                    <p className="large-money">
                      {formatMoney(l.principal_minor, l.currency_code, locale)}
                    </p>
                    <p>{l.comment}</p>
                    {l.due_on && (
                      <small>
                        {t("deadline")}: {l.due_on}
                      </small>
                    )}
                    {l.settlement_operation && (
                      <p className="hint">
                        {t("settlement")}: {l.settlement_operation.occurred_on}
                      </p>
                    )}
                    <div className="card-actions">
                      <button onClick={() => setEditor(l)}>{t("edit")}</button>
                      {l.status === "open" && (
                        <button
                          className="primary"
                          onClick={() => setSettle(l)}
                        >
                          {t("settle")}
                        </button>
                      )}
                      {l.status === "settled" ? (
                        <button
                          onClick={() => {
                            if (confirm(t("voidSettlement") + "?"))
                              void mutation
                                .submit(
                                  `/liabilities/${l.id}/void-settlement`,
                                  "POST",
                                  { expected_revision: l.revision },
                                )
                                .catch(() => {});
                          }}
                        >
                          {t("voidSettlement")}
                        </button>
                      ) : (
                        <button
                          onClick={() =>
                            void mutation
                              .submit(
                                `/liabilities/${l.id}/${l.status === "cancelled" ? "resume" : "cancel"}`,
                                "POST",
                                { expected_revision: l.revision },
                              )
                              .catch(() => {})
                          }
                        >
                          {t(
                            l.status === "cancelled"
                              ? "resume"
                              : "cancelObject",
                          )}
                        </button>
                      )}
                    </div>
                  </section>
                ))}
              </div>
            ) : (
              <Empty />
            )}
            <Pager page={page} pages={d.pagination.pages} onPage={setPage} />
          </>
        )}
      </State>
      {editor && (
        <Editor
          title={t(editor === "new" ? "new" : "edit") + " " + t("liabilities")}
          path={"/liabilities" + (editor === "new" ? "" : "/" + editor.id)}
          method={editor === "new" ? "POST" : "PATCH"}
          revision={editor === "new" ? undefined : editor.revision}
          fields={fields(editor === "new" ? undefined : editor)}
          hint={t("debtHint")}
          onClose={() => setEditor(null)}
        />
      )}{" "}
      {settle && (
        <OperationForm
          session={session}
          liability={settle}
          onClose={() => setSettle(null)}
        />
      )}
    </>
  );
}
export function BudgetsPage({ session }: { session: Session }) {
  const t = useT(),
    locale = useContext(LocaleContext),
    mutation = useCommand();
  const [month, setMonth] = useState(
      today(session.settings.timezone).slice(0, 7),
    ),
    [page, setPage] = useState(1),
    [editor, setEditor] = useState<Budget | "new" | null>(null),
    [template, setTemplate] = useState<BudgetTemplate | "new" | null>(null);
  const q = useList<Budget>(`/budgets?month=${month}&page=${page}`),
    cats = useAllList<Category>("/categories"),
    templates = useAllList<BudgetTemplate>("/budget-templates");
  const catOptions = cats.data?.data.items
    .filter((c) => c.kind === "expense" && !c.archived_at)
    .map((c) => ({ value: c.id, label: categoryName(c, t) }));
  const name = (id: string) => {
    const c = cats.data?.data.items.find((c) => c.id === id);
    return c ? categoryName(c, t) : "…";
  };
  const fields = (b?: Budget): Field[] => [
    ...(!b
      ? [
          {
            key: "category_id",
            label: t("category"),
            type: "select",
            required: true,
            options: catOptions,
          } as Field,
          {
            key: "period_month",
            label: t("month"),
            type: "month",
            required: true,
            initial: month,
          } as Field,
        ]
      : []),
    moneyField("limit_minor", t("limit"), b?.limit_minor),
    {
      key: "currency_code",
      label: t("currency"),
      type: "select",
      required: true,
      initial: b?.currency_code ?? session.settings.base_currency_code,
      options: currencies.map((c) => ({ value: c, label: c })),
    },
  ];
  return (
    <>
      <div className="page-heading">
        <h1>{t("budgets")}</h1>
        <div className="inline">
          <input
            aria-label={t("month")}
            type="month"
            value={month}
            onChange={(e) => {
              setMonth(e.target.value);
              setPage(1);
            }}
          />
          <button className="primary" onClick={() => setEditor("new")}>
            <Plus size={18} />
            {t("new")}
          </button>
        </div>
      </div>
      <div className="inline wrap">
        <button
          disabled={mutation.isPending}
          onClick={() =>
            void mutation
              .submit("/budgets/ensure-month", "POST", {
                period_month: month + "-01",
              })
              .catch(() => {})
          }
        >
          {t("applyTemplates")}
        </button>
        <button onClick={() => setTemplate("new")}>{t("repeat")}</button>
      </div>
      <ErrorMessage error={mutation.error} />
      <State query={q}>
        {(d) => (
          <>
            {d.items.length ? (
              <div className="entity-grid">
                {d.items.map((b) => (
                  <section className="entity-card" key={b.id}>
                    <div className="card-top">
                      <h2>{name(b.category_id)}</h2>
                      <span>{b.currency_code}</span>
                    </div>
                    {b.disabled ? (
                      <p>{t("disabled")}</p>
                    ) : (
                      <>
                        <p className="large-money">
                          {formatMoney(b.fact_minor, b.currency_code, locale)}{" "}
                          <small>
                            /{" "}
                            {formatMoney(
                              b.limit_minor,
                              b.currency_code,
                              locale,
                            )}
                          </small>
                        </p>
                        <Progress value={b.progress} />
                        <p
                          className={
                            BigInt(b.remaining_minor) < 0n ? "warning" : ""
                          }
                        >
                          {t(
                            BigInt(b.remaining_minor) < 0n
                              ? "overBudget"
                              : "remaining",
                          )}
                          :{" "}
                          {formatMoney(
                            b.remaining_minor,
                            b.currency_code,
                            locale,
                          )}
                        </p>
                        {Object.entries(b.other_currencies).map(([c, a]) => (
                          <p className="warning" key={c}>
                            {formatMoney(
                              a!,
                              c as Budget["currency_code"],
                              locale,
                            )}{" "}
                            · {t("otherCurrencies")}
                          </p>
                        ))}
                        <div className="card-actions">
                          <button onClick={() => setEditor(b)}>
                            {t("edit")}
                          </button>
                          <button
                            onClick={() => {
                              if (
                                confirm(
                                  t("delete") + " " + name(b.category_id) + "?",
                                )
                              )
                                void mutation
                                  .submit("/budgets/" + b.id, "DELETE", {
                                    expected_revision: b.revision,
                                  })
                                  .catch(() => {});
                            }}
                          >
                            {t("delete")}
                          </button>
                        </div>
                      </>
                    )}
                  </section>
                ))}
              </div>
            ) : (
              <Empty />
            )}
            <Pager page={page} pages={d.pagination.pages} onPage={setPage} />
          </>
        )}
      </State>
      <section className="panel">
        <h2>{t("templates")}</h2>
        <State query={templates}>
          {(d) => (
            <>
              {d.items.length ? (
                d.items.map((b) => (
                  <div className="summary-line" key={b.id}>
                    <span>
                      {name(b.category_id)} · {b.start_month}{" "}
                      {b.stop_month ? "→ " + b.stop_month : ""}
                    </span>
                    <strong>
                      {formatMoney(b.limit_minor, b.currency_code, locale)}
                    </strong>
                    <button onClick={() => setTemplate(b)}>{t("edit")}</button>
                    {!b.stop_month && (
                      <button
                        onClick={() => {
                          const stop = prompt(t("stopMonth"), month);
                          if (stop)
                            void mutation
                              .submit(
                                "/budget-templates/" + b.id + "/stop",
                                "POST",
                                {
                                  expected_revision: b.revision,
                                  stop_month: stop + "-01",
                                },
                              )
                              .catch(() => {});
                        }}
                      >
                        {t("stop")}
                      </button>
                    )}
                  </div>
                ))
              ) : (
                <Empty />
              )}
            </>
          )}
        </State>
      </section>
      {editor && (
        <Editor
          title={t(editor === "new" ? "new" : "edit") + " " + t("budgets")}
          path={"/budgets" + (editor === "new" ? "" : "/" + editor.id)}
          method={editor === "new" ? "POST" : "PATCH"}
          revision={editor === "new" ? undefined : editor.revision}
          fields={fields(editor === "new" ? undefined : editor)}
          onClose={() => setEditor(null)}
        />
      )}{" "}
      {template && (
        <Editor
          title={t("repeat")}
          path={
            "/budget-templates" + (template === "new" ? "" : "/" + template.id)
          }
          method={template === "new" ? "POST" : "PATCH"}
          revision={template === "new" ? undefined : template.revision}
          fields={[
            ...(template === "new"
              ? [
                  {
                    key: "category_id",
                    label: t("category"),
                    type: "select",
                    required: true,
                    options: catOptions,
                  } as Field,
                  {
                    key: "start_month",
                    label: t("startMonth"),
                    type: "month",
                    required: true,
                    initial: month,
                  } as Field,
                ]
              : []),
            moneyField(
              "limit_minor",
              t("limit"),
              template === "new" ? undefined : template.limit_minor,
            ),
            {
              key: "currency_code",
              label: t("currency"),
              type: "select",
              required: true,
              initial:
                template === "new"
                  ? session.settings.base_currency_code
                  : template.currency_code,
              options: currencies.map((c) => ({ value: c, label: c })),
            },
          ]}
          onClose={() => setTemplate(null)}
        />
      )}
    </>
  );
}
