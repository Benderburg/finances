import { useContext, useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Plus } from "lucide-react";
import { get, request } from "../data/api";
import {
  currencies,
  formatMoney,
  inputMoney,
  parseMoney,
  quoteTarget,
  today,
} from "../domain/money";
import type {
  Account,
  Category,
  Goal,
  Liability,
  List,
  Operation,
  Session,
} from "../domain/types";
import { categoryName, LocaleContext, useT } from "../i18n";
import {
  Editor,
  ErrorMessage,
  Modal,
  useCommand,
  useAllList,
} from "../components/ui";

export function OperationForm({
  session,
  onClose,
  operation,
  goal,
  liability,
  toAccount,
  initialType = "expense",
}: {
  session: Session;
  onClose: () => void;
  operation?: Operation;
  goal?: Goal;
  liability?: Liability;
  toAccount?: string;
  initialType?: Operation["type"];
}) {
  const t = useT(),
    locale = useContext(LocaleContext);
  const mutation = useCommand();
  const [type, setType] = useState<Operation["type"]>(
    operation?.type ?? (toAccount ? "transfer" : initialType),
  );
  const [account, setAccount] = useState(operation?.account_id ?? "");
  const [from, setFrom] = useState(operation?.from_account_id ?? "");
  const [to, setTo] = useState(operation?.to_account_id ?? toAccount ?? "");
  const [category, setCategory] = useState(operation?.category_id ?? "");
  const [amount, setAmount] = useState(
    operation ? inputMoney(operation.amount_minor) : "",
  );
  const [received, setReceived] = useState(
    operation?.target_amount_minor
      ? inputMoney(operation.target_amount_minor)
      : "",
  );
  const [rate, setRate] = useState(operation?.quoted_rate ?? "");
  const [mode, setMode] = useState(operation?.quoted_rate ? "rate" : "amount");
  const [date, setDate] = useState(
    operation?.occurred_on ?? today(session.settings.timezone),
  );
  const [description, setDescription] = useState(operation?.description ?? "");
  const [complete, setComplete] = useState(
    operation?.goal_completion_requested ?? false,
  );
  const [quickCategory, setQuickCategory] = useState(false);
  const [newAccount, setNewAccount] = useState(false);
  const [error, setError] = useState<unknown>();
  const [referenceQuote, setReferenceQuote] = useState<{
    key: string;
    amount: string | null;
  }>();
  const [quoteBusy, setQuoteBusy] = useState(false);
  const accounts = useAllList<Account>("/accounts"),
    categories = useAllList<Category>("/categories");
  const allAccounts = accounts.data?.data.items ?? [],
    allCategories = categories.data?.data.items ?? [];
  const effectiveType = liability
    ? liability.kind === "receivable"
      ? "income"
      : "expense"
    : goal
      ? "expense"
      : type;
  const pair = effectiveType === "transfer" || effectiveType === "exchange";
  const source = allAccounts.find(
      (a) => a.id === (pair ? from : (goal?.savings_account_id ?? account)),
    ),
    target = allAccounts.find((a) => a.id === to);
  const exchange =
    pair && source && target && source.currency_code !== target.currency_code;
  const quoteKey = [
    amount,
    source?.currency_code,
    target?.currency_code,
    date,
  ].join("|");
  async function reference() {
    if (!source || !target) return;
    setQuoteBusy(true);
    setError(undefined);
    try {
      const r = await request<{ data: { amount_minor: string | null } }>(
        "/api/v1/operations/quote",
        "POST",
        {
          amount_minor: parseMoney(amount),
          currency_code: source.currency_code,
          target_currency_code: target.currency_code,
          date,
        },
      );
      setReferenceQuote({ key: quoteKey, amount: r.data.amount_minor });
    } catch (e) {
      setError(e);
    } finally {
      setQuoteBusy(false);
    }
  }
  useEffect(() => {
    if (!account && allAccounts.length)
      setAccount(allAccounts.find((a) => !a.archived_at)?.id ?? "");
  }, [allAccounts, account]);
  let quote = "";
  if (exchange && mode === "rate") {
    try {
      quote = inputMoney(quoteTarget(parseMoney(amount), rate));
    } catch {
      quote = "";
    }
  }
  const activeAccounts = allAccounts.filter(
    (a) =>
      !a.archived_at ||
      a.id === operation?.account_id ||
      a.id === operation?.from_account_id ||
      a.id === operation?.to_account_id,
  );
  const eligibleCategories = allCategories.filter(
    (c) =>
      c.kind === effectiveType &&
      c.system_code !== "goal_expense" &&
      (!c.archived_at || c.id === operation?.category_id),
  );
  const goalQuery = useQuery({
    queryKey: ["goal", operation?.goal_id],
    queryFn: () => get<Goal>(`/goals/${operation?.goal_id}`),
    enabled: Boolean(operation?.goal_id && !goal),
  });
  const actualGoal = goal ?? goalQuery.data?.data;
  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(undefined);
    try {
      const body: Record<string, unknown> = { occurred_on: date, description };
      let path = "/operations",
        method = operation ? "PATCH" : "POST";
      if (operation) {
        path += `/${operation.id}`;
        body.expected_revision = operation.revision;
      }
      if (actualGoal) {
        path = `/goals/${actualGoal.id}/${operation ? `expenses/${operation.id}` : "spend"}`;
        method = operation ? "PATCH" : "POST";
        Object.assign(body, {
          expected_revision: actualGoal.revision,
          amount_minor: parseMoney(amount),
          goal_completion_requested: complete,
        });
        if (operation) body.expected_operation_revision = operation.revision;
      } else if (liability) {
        path = `/liabilities/${liability.id}/settle`;
        Object.assign(body, {
          expected_revision: liability.revision,
          account_id: account,
          category_id: category,
        });
      } else if (pair) {
        Object.assign(body, {
          type: exchange ? "exchange" : "transfer",
          from_account_id: from,
          to_account_id: to,
          amount_minor: parseMoney(amount),
        });
        if (exchange) {
          if (mode === "rate") {
            body.quoted_rate = rate.replace(",", ".");
            body.target_amount_minor = null;
          } else {
            body.target_amount_minor = parseMoney(received);
            body.quoted_rate = null;
          }
        } else {
          body.target_amount_minor = parseMoney(amount);
          body.quoted_rate = null;
        }
      } else
        Object.assign(body, {
          type: effectiveType,
          account_id: account,
          category_id: category,
          amount_minor: parseMoney(amount),
        });
      await mutation.submit(path, method, body);
      onClose();
    } catch (e) {
      setError(e);
    }
  }
  const selectAccounts = (
    value: string,
    change: (v: string) => void,
    exclude?: string,
    label = t("account"),
  ) => (
    <select
      aria-label={label}
      value={value}
      required
      onChange={(e) => change(e.target.value)}
    >
      <option value="">—</option>
      {activeAccounts
        .filter(
          (a) =>
            a.id !== exclude &&
            (!liability || a.currency_code === liability.currency_code),
        )
        .map((a) => (
          <option key={a.id} value={a.id}>
            {a.name} · {a.currency_code}
          </option>
        ))}
    </select>
  );
  const close = () => {
    if (!mutation.isPending && !mutation.uncertain) onClose();
  };
  return (
    <Modal
      title={
        liability
          ? t("settle")
          : actualGoal
            ? t("spend")
            : operation
              ? t("edit")
              : t("newOperation")
      }
      onClose={close}
    >
      <form onSubmit={submit}>
        {!operation && !actualGoal && !liability && (
          <div className="tabs">
            {(["expense", "income", "transfer"] as const).map((v) => (
              <button
                type="button"
                disabled={mutation.isPending || mutation.uncertain}
                className={type === v ? "selected" : ""}
                onClick={() => {
                  setType(v);
                  setCategory("");
                }}
                key={v}
              >
                {t(v)}
              </button>
            ))}
          </div>
        )}
        <fieldset disabled={mutation.isPending || mutation.uncertain}>
          {!pair && !actualGoal && (
            <label>
              <span>{t("account")}</span>
              {selectAccounts(account, setAccount)}
            </label>
          )}
          {pair && (
            <div className="form-grid">
              <label>
                <span>{t("source")}</span>
                {selectAccounts(
                  from,
                  (v) => {
                    setFrom(v);
                    setRate("");
                    setReceived("");
                  },
                  to,
                  t("source"),
                )}
              </label>
              <label>
                <span>{t("destination")}</span>
                {selectAccounts(
                  to,
                  (v) => {
                    setTo(v);
                    setRate("");
                    setReceived("");
                  },
                  from,
                  t("destination"),
                )}
              </label>
            </div>
          )}
          {source && (
            <p className="hint">
              {source.name} · {t("available")}:{" "}
              {formatMoney(source.balance_minor, source.currency_code, locale)}
            </p>
          )}
          {!liability && (
            <label>
              <span>
                {t("amount")}
                {source ? " · " + source.currency_code : ""}
              </span>
              <input
                autoFocus
                inputMode="decimal"
                required
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
            </label>
          )}
          {liability && (
            <div className="fixed-amount">
              {formatMoney(
                liability.principal_minor,
                liability.currency_code,
                locale,
              )}
            </div>
          )}
          {exchange && (
            <>
              <div className="tabs">
                <button
                  type="button"
                  className={mode === "amount" ? "selected" : ""}
                  onClick={() => setMode("amount")}
                >
                  {t("amountMode")}
                </button>
                <button
                  type="button"
                  className={mode === "rate" ? "selected" : ""}
                  onClick={() => setMode("rate")}
                >
                  {t("rateMode")}
                </button>
              </div>
              <p className="subtle">{t("fxIndicative")}</p>
              <button
                type="button"
                disabled={quoteBusy || !amount}
                onClick={() => void reference()}
              >
                {t(quoteBusy ? "loading" : "fxQuote")}
              </button>
              {referenceQuote?.key === quoteKey && (
                <p role="status">
                  {referenceQuote.amount !== null && target
                    ? formatMoney(
                        referenceQuote.amount,
                        target.currency_code,
                        locale,
                      )
                    : t("fxMissing")}
                </p>
              )}
              {mode === "rate" ? (
                <>
                  <label>
                    <span>
                      {t("rate")} · {source?.currency_code}/
                      {target?.currency_code}
                    </span>
                    <input
                      inputMode="decimal"
                      required
                      value={rate}
                      onChange={(e) => setRate(e.target.value)}
                    />
                  </label>
                  <p className="hint">
                    {t("received")}: {quote || "—"} {target?.currency_code}
                  </p>
                </>
              ) : (
                <label>
                  <span>
                    {t("received")} · {target?.currency_code}
                  </span>
                  <input
                    inputMode="decimal"
                    required
                    value={received}
                    onChange={(e) => setReceived(e.target.value)}
                  />
                </label>
              )}
            </>
          )}
          {!pair && !actualGoal && (
            <label>
              <span>{t("category")}</span>
              <div className="inline">
                {" "}
                <select
                  aria-label={t("category")}
                  required
                  value={category}
                  onChange={(e) => setCategory(e.target.value)}
                >
                  <option value="">—</option>
                  {eligibleCategories.map((c) => (
                    <option value={c.id} key={c.id}>
                      {categoryName(c, t)}
                    </option>
                  ))}
                </select>
                <button
                  type="button"
                  aria-label={t("new") + " " + t("category")}
                  onClick={() => setQuickCategory(true)}
                >
                  <Plus size={18} />
                </button>
              </div>
            </label>
          )}
          <label>
            <span>{t("date")}</span>
            <input
              type="date"
              required
              value={date}
              onChange={(e) => setDate(e.target.value)}
            />
          </label>
          {date > today(session.settings.timezone) && (
            <p className="warning">{t("futureWarning")}</p>
          )}
          <label>
            <span>{t("description")}</span>
            <textarea
              maxLength={2000}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </label>
          {actualGoal && (
            <label className="check">
              <input
                type="checkbox"
                checked={complete}
                onChange={(e) => setComplete(e.target.checked)}
              />
              {t("complete")}
            </label>
          )}
        </fieldset>
        <ErrorMessage error={error ?? accounts.error ?? categories.error} />
        {!allAccounts.some((a) => !a.archived_at) && (
          <button type="button" onClick={() => setNewAccount(true)}>
            {t("new")} {t("account")}
          </button>
        )}
        <footer className="form-actions">
          <button
            type="button"
            disabled={mutation.isPending || mutation.uncertain}
            onClick={close}
          >
            {t("cancel")}
          </button>
          <button
            className="primary"
            type="submit"
            disabled={mutation.isPending || !navigator.onLine}
          >
            {mutation.isPending
              ? t("loading")
              : mutation.uncertain
                ? t("retry")
                : t("save")}
          </button>
        </footer>
      </form>
      {quickCategory && (
        <Editor
          title={t("new") + " " + t("category")}
          path="/categories"
          fields={[
            { key: "name", label: t("name"), required: true },
            {
              key: "kind",
              label: t("kind"),
              type: "select",
              initial: effectiveType,
              required: true,
              options: [
                { value: "income", label: t("income") },
                { value: "expense", label: t("expense") },
              ],
            },
          ]}
          onSaved={(data) => {
            const c = data as Category;
            setCategory(c.id);
          }}
          onClose={() => setQuickCategory(false)}
        />
      )}
      {newAccount && (
        <Editor
          title={t("new") + " " + t("account")}
          path="/accounts"
          fields={[
            { key: "name", label: t("name"), required: true },
            {
              key: "kind",
              label: t("kind"),
              type: "select",
              required: true,
              initial: "regular",
              options: [
                { value: "regular", label: t("regular") },
                { value: "savings", label: t("savingsKind") },
              ],
            },
            {
              key: "currency_code",
              label: t("currency"),
              type: "select",
              initial: "MDL",
              required: true,
              options: currencies.map((c) => ({ value: c, label: c })),
            },
            {
              key: "opening_balance_minor",
              label: t("opening"),
              type: "money",
              initial: "0",
              allowZero: true,
              required: true,
            },
          ]}
          onSaved={(data) => {
            const a = data as Account;
            setAccount(a.id);
          }}
          onClose={() => setNewAccount(false)}
        />
      )}
    </Modal>
  );
}
