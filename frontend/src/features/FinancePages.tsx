import { Fragment, useContext, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Link, useSearchParams } from "react-router-dom";
import {
  Plus,
  ArrowDownLeft,
  ArrowUpRight,
  ArrowLeftRight,
  Wallet,
  Target,
  PiggyBank,
  HandCoins,
  CalendarDays,
  ShoppingBag,
  Utensils,
  Car,
  HeartPulse,
  House,
} from "lucide-react";
import type {
  Account,
  Budget,
  BudgetTemplate,
  Category,
  Currency,
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
import { MoneyAmount, type OpenOperation } from "../components/finance-ui";
import { useConfirm } from "../components/Feedback";
import { SavingsOverview } from "./SavingsOverview";
import { OperationForm } from "./OperationForm";
import { ValuationNote } from "./FxViews";

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
  const CategoryIcon =
    (
      {
        food: Utensils,
        shopping: ShoppingBag,
        transport: Car,
        health: HeartPulse,
        housing: House,
      } as Record<string, typeof Wallet>
    )[c?.system_code ?? ""] ?? ArrowUpRight;
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
          <CategoryIcon size={19} />
        )}
      </span>
      <span className="operation-text">
        <strong>
          {pair
            ? `${name(o.from_account_id)} → ${name(o.to_account_id)}`
            : o.description || (c ? categoryName(c, t) : t(o.type))}
        </strong>
        <small>
          {new Intl.DateTimeFormat(locale, {
            day: "numeric",
            month: "short",
          }).format(new Date(o.occurred_on + "T12:00:00"))}{" "}
          · {c ? categoryName(c, t) : t(o.type)}
          {o.liability_id ? " · " + t("settlement") : ""}
          {o.goal_id ? " · " + t("goals") : ""}
          {o.status === "voided" ? " · " + t("voided") : ""}
        </small>
      </span>
      <span className={`operation-amount ${o.type}`}>
        {o.type === "expense" ? "−" : o.type === "income" ? "+" : ""}
        <MoneyAmount amount={o.amount_minor} currency={o.currency_code} />
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
export function OperationsPage({
  openOperation,
}: {
  openOperation: (o?: Operation) => void;
}) {
  const t = useT(),
    locale = useContext(LocaleContext);
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
        <div>
          <h1>{t("operations")}</h1>
          <p className="page-description">{t("operationsHint")}</p>
        </div>
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
                d.items.map((o, index) => (
                  <Fragment key={o.id}>
                    {(index === 0 ||
                      d.items[index - 1].occurred_on !== o.occurred_on) && (
                      <h3 className="transaction-day">
                        {new Intl.DateTimeFormat(locale, {
                          day: "numeric",
                          month: "long",
                          year: "numeric",
                        }).format(new Date(o.occurred_on + "T12:00:00"))}
                      </h3>
                    )}
                    <OperationRow
                      o={o}
                      accounts={ac.data?.data.items}
                      categories={cats.data?.data.items}
                      onClick={() => openOperation(o)}
                      key={o.id}
                    />
                  </Fragment>
                ))
              ) : (
                <Empty
                  title={params.size ? "noMatches" : "emptyOperations"}
                  hint={params.size ? "noMatchesHint" : "emptyOperationsHint"}
                  action={
                    params.size ? (
                      <button onClick={() => setParams({})}>
                        {t("clearFilters")}
                      </button>
                    ) : (
                      <button
                        className="primary"
                        onClick={() => openOperation()}
                      >
                        {t("newOperation")}
                      </button>
                    )
                  }
                />
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
  const confirm = useConfirm();
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
                  onClick={async () => {
                    if (
                      await confirm(
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
  savingsOnly = false,
}: {
  session: Session;
  savingsOnly?: boolean;
  openOperation: OpenOperation;
}) {
  const confirm = useConfirm();
  const t = useT(),
    locale = useContext(LocaleContext),
    mutation = useCommand();
  const [params, setParams] = useSearchParams();
  const [page, setPage] = useState(1),
    [kind, setKind] = useState(savingsOnly ? "savings" : ""),
    [archive, setArchive] = useState("all"),
    [editor, setEditor] = useState<Account | "new" | null>(
      params.has("new") ? "new" : null,
    );
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
      initial: a?.kind ?? (savingsOnly ? "savings" : "regular"),
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
        <div>
          <h1>{t(savingsOnly ? "savings" : "accounts")}</h1>
          <p className="page-description">
            {t(savingsOnly ? "savingsHint" : "accountsHint")}
          </p>
        </div>
        <button className="primary" onClick={() => setEditor("new")}>
          <Plus size={18} />
          {t("new")}
        </button>
      </div>
      {savingsOnly && <SavingsOverview session={session} />}
      <div className="filters">
        <select
          hidden={savingsOnly}
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
                  <section
                    className={`entity-card account-card currency-${a.currency_code.toLowerCase()}`}
                    key={a.id}
                  >
                    <div className="card-top">
                      <span className="entity-icon">
                        {a.kind === "savings" ? (
                          <PiggyBank size={22} />
                        ) : (
                          <Wallet size={22} />
                        )}
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
                      <MoneyAmount
                        amount={a.balance_minor}
                        currency={a.currency_code}
                      />
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
                          {t(a.kind === "savings" ? "topUp" : "transfer")}
                        </button>
                      )}
                      {a.opening_balance_minor === "0" &&
                        a.balance_minor === "0" &&
                        !a.goal_id && (
                          <button
                            onClick={async () => {
                              if (
                                await confirm(t("delete") + " " + a.name + "?")
                              )
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
              <Empty
                title={savingsOnly ? "emptySavings" : "emptyAccounts"}
                hint={savingsOnly ? "emptySavingsHint" : "emptyAccountsHint"}
                action={
                  <button className="primary" onClick={() => setEditor("new")}>
                    {t("createAccount")}
                  </button>
                }
              />
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
          onClose={() => {
            setEditor(null);
            if (params.has("new")) setParams({});
          }}
        />
      )}
    </>
  );
}
export function GoalsPage({ session }: { session: Session }) {
  const confirm = useConfirm();
  const t = useT(),
    locale = useContext(LocaleContext),
    mutation = useCommand();
  const [params, setParams] = useSearchParams();
  const [page, setPage] = useState(1),
    [editor, setEditor] = useState<Goal | "new" | null>(
      params.has("new") ? "new" : null,
    ),
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
        <div>
          <h1>{t("goals")}</h1>
          <p className="page-description">{t("goalsHint")}</p>
        </div>
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
                  <section className="entity-card goal-card" key={g.id}>
                    <div className="card-top">
                      <span className="goal-icon">{g.icon ?? <Target />}</span>
                      <span className="badge">{t(g.status)}</span>
                    </div>
                    <h2>{g.name}</h2>
                    <p className="large-money">
                      <MoneyAmount
                        amount={g.saved_now_minor}
                        currency={g.currency_code}
                      />
                      <small>
                        {" "}
                        /{" "}
                        <MoneyAmount
                          amount={g.target_amount_minor}
                          currency={g.currency_code}
                        />
                      </small>
                    </p>
                    <div className="goal-progress-label">
                      <span>{t("funded")}</span>
                      <strong>{g.progress}%</strong>
                    </div>
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
                        <button
                          className="primary"
                          disabled={!g.savings_account_id}
                          onClick={() => setTopUp(g.savings_account_id)}
                        >
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
                            onClick={async () => {
                              if (
                                await confirm(t("delete") + " " + g.name + "?")
                              )
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
              <Empty
                title="emptyGoals"
                hint="emptyGoalsHint"
                action={
                  <button className="primary" onClick={() => setEditor("new")}>
                    {t("createGoal")}
                  </button>
                }
              />
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
          onClose={() => {
            setEditor(null);
            if (params.has("new")) setParams({});
          }}
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
  const confirm = useConfirm();
  const t = useT(),
    locale = useContext(LocaleContext),
    mutation = useCommand();
  const [kind, setKind] = useState("");
  const [page, setPage] = useState(1),
    [editor, setEditor] = useState<Liability | "new" | null>(null),
    [settle, setSettle] = useState<Liability | null>(null);
  const q = useList<Liability>(
    `/liabilities?page=${page}${kind ? "&kind=" + kind : ""}`,
  );
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
      <p className="page-description">{t("debtHint")}</p>
      <div className="tabs debt-tabs">
        {["", "receivable", "payable", "credit"].map((value) => (
          <button
            className={kind === value ? "selected" : ""}
            aria-pressed={kind === value}
            key={value}
            onClick={() => {
              setKind(value);
              setPage(1);
            }}
          >
            {t(value || "allDebts")}
          </button>
        ))}
      </div>
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
                  <section
                    className={`entity-card debt-card ${l.kind}`}
                    key={l.id}
                  >
                    <div className="card-top">
                      <span className="debt-kind">
                        <HandCoins size={19} />
                        {t(l.kind)}
                      </span>
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
                          onClick={async () => {
                            if (await confirm(t("voidSettlement") + "?"))
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
              <Empty
                title="emptyDebts"
                hint="emptyDebtsHint"
                action={
                  <button className="primary" onClick={() => setEditor("new")}>
                    {t("createDebt")}
                  </button>
                }
              />
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
  const confirm = useConfirm();
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
                        <ValuationNote value={b.valuation} />
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
                          hidden={b.valuation.incomplete}
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
                          <p className="subtle" key={c}>
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
                            onClick={async () => {
                              if (
                                await confirm(
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
              <Empty
                title="emptyBudgets"
                hint="emptyBudgetsHint"
                action={
                  <button className="primary" onClick={() => setEditor("new")}>
                    {t("createBudget")}
                  </button>
                }
              />
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
                <Empty
                  title="emptyBudgets"
                  hint="emptyBudgetsHint"
                  action={
                    <button
                      className="primary"
                      onClick={() => setEditor("new")}
                    >
                      {t("new")}
                    </button>
                  }
                />
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
