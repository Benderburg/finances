import { useContext, useState } from "react";
import { download, request } from "../data/api";
import { categoryName, LocaleContext, useT } from "../i18n";
import { ErrorMessage, useAllList, useCommand, Pager } from "../components/ui";
import type { Account, Category, Operation } from "../domain/types";
import { formatMoney } from "../domain/money";

interface CsvRow {
  number: number;
  errors: string[];
  possible_duplicate: boolean;
  operation?: Operation;
  status?: string;
}
interface CsvPreview {
  encoding: string;
  delimiter: string;
  format: string;
  headers: string[];
  sample: Record<string, string>[];
  source_accounts: { id: string; name: string; currency_code: string }[];
  count: number;
  needs_confirmation: boolean;
  preview_id?: string;
  rows?: CsvRow[];
}
const mapKeys = [
  "date",
  "amount",
  "direction",
  "currency",
  "description",
  "category",
  "transaction_id",
];
export function CsvPage() {
  const t = useT(),
    locale = useContext(LocaleContext),
    mutation = useCommand();
  const accounts = useAllList<Account>("/accounts"),
    categories = useAllList<Category>("/categories");
  const [file, setFile] = useState<File | null>(null),
    [preview, setPreview] = useState<CsvPreview>(),
    [error, setError] = useState<unknown>(),
    [busy, setBusy] = useState(false);
  const [encoding, setEncoding] = useState("UTF-8"),
    [delimiter, setDelimiter] = useState(","),
    [dateFormat, setDateFormat] = useState("Y-m-d"),
    [decimal, setDecimal] = useState(".");
  const [account, setAccount] = useState(""),
    [incomeCategory, setIncomeCategory] = useState(""),
    [expenseCategory, setExpenseCategory] = useState("");
  const [mapping, setMapping] = useState<Record<string, string>>({}),
    [accountMappings, setAccountMappings] = useState<Record<string, string>>(
      {},
    );
  const [incomeValue, setIncomeValue] = useState("income"),
    [expenseValue, setExpenseValue] = useState("expense"),
    [confirmed, setConfirmed] = useState(false);
  const [selected, setSelected] = useState<number[]>([]),
    [acceptDuplicates, setAcceptDuplicates] = useState(false),
    [page, setPage] = useState(1),
    [done, setDone] = useState<number | null>(null),
    [safe, setSafe] = useState(true);
  async function inspect(detect = false, auto = false) {
    if (!file) return;
    setBusy(true);
    setError(undefined);
    setDone(null);
    try {
      const body = new FormData();
      body.append("file", file);
      body.append(
        "options",
        JSON.stringify(
          detect
            ? auto
              ? {}
              : { encoding, delimiter }
            : {
                confirmed: true,
                encoding,
                delimiter,
                date_format: dateFormat,
                decimal_separator: decimal,
                account_id: account || null,
                mapping,
                account_mappings: accountMappings,
                income_category_id: incomeCategory || null,
                expense_category_id: expenseCategory || null,
                income_value: incomeValue,
                expense_value: expenseValue,
              },
        ),
      );
      const r = await request<{ data: CsvPreview }>(
        "/api/v1/csv/preview",
        "POST",
        body,
      );
      setPreview(r.data);
      setPage(1);
      if (detect) {
        setEncoding(r.data.encoding);
        setDelimiter(r.data.delimiter);
        setConfirmed(false);
        const m: Record<string, string> = {};
        for (const key of mapKeys)
          m[key] = r.data.headers.find((h) => h.toLowerCase() === key) ?? "";
        setMapping(m);
        setAccountMappings({});
      }
      setSelected(
        (r.data.rows ?? [])
          .filter((r) => !r.errors.length && !r.possible_duplicate)
          .map((r) => r.number),
      );
      setAcceptDuplicates(false);
    } catch (e) {
      setError(e);
    } finally {
      setBusy(false);
    }
  }
  async function apply() {
    setError(undefined);
    try {
      await mutation.submit("/csv/apply", "POST", {
        preview_id: preview?.preview_id,
        selected_rows: selected,
        accept_possible_duplicates: acceptDuplicates,
      });
      setDone(selected.length);
      setPreview(undefined);
      setFile(null);
      setSelected([]);
    } catch (e) {
      setError(e);
    }
  }
  const locked = busy || mutation.isPending || mutation.uncertain;
  const originalAccounts = preview?.source_accounts ?? [];
  const categorySelect = (
    kind: "income" | "expense",
    value: string,
    set: (v: string) => void,
  ) => (
    <label>
      <span>
        {t(kind)} · {t("category")}
      </span>
      <select
        aria-label={`${t(kind)} · ${t("category")}`}
        value={value}
        onChange={(e) => set(e.target.value)}
      >
        <option value="">—</option>
        {categories.data?.data.items
          .filter(
            (c) =>
              c.kind === kind &&
              !c.archived_at &&
              c.system_code !== "goal_expense",
          )
          .map((c) => (
            <option key={c.id} value={c.id}>
              {categoryName(c, t)}
            </option>
          ))}
      </select>
    </label>
  );
  return (
    <>
      <div className="page-heading">
        <h1>{t("csvTitle")}</h1>
      </div>
      <section className="panel">
        <h2>{t("csvExport")}</h2>
        <p>{t("csvExportHint")}</p>
        <label className="check">
          <input
            type="checkbox"
            checked={safe}
            onChange={(e) => setSafe(e.target.checked)}
          />
          {t("csvSafe")}
        </label>
        <button
          onClick={() =>
            download(`/api/v1/operations/export.csv?safe=${safe ? 1 : 0}`)
          }
        >
          {t("csvExport")}
        </button>
      </section>
      <section className="panel">
        <h2>{t("csvImport")}</h2>
        <p>{t("csvAdditive")}</p>
        <p className="subtle">{t("csvLimits")}</p>
        {done !== null && (
          <p className="success" role="status">
            {t("csvImported")}: {done}
          </p>
        )}
        <fieldset disabled={locked}>
          <label>
            <span>{t("csvFile")}</span>
            <input
              type="file"
              accept=".csv,.txt,text/csv"
              onChange={(e) => {
                setFile(e.target.files?.[0] ?? null);
                setPreview(undefined);
                setDone(null);
              }}
            />
          </label>
          <button
            disabled={!file || file.size > 5 * 1024 * 1024}
            onClick={() => void inspect(true, true)}
          >
            {t("csvDetect")}
          </button>
          {file && file.size > 5 * 1024 * 1024 && (
            <p className="error">{t("CSV_LIMIT")}</p>
          )}
        </fieldset>
        <ErrorMessage
          error={error ?? mutation.error ?? accounts.error ?? categories.error}
        />
        {preview && (
          <>
            <p>
              {preview.format} · {preview.count} {t("csvRows")}
            </p>
            {preview.needs_confirmation ? (
              <>
                <fieldset disabled={locked} className="csv-mapping">
                  <div className="filters">
                    <label>
                      <span>{t("csvEncoding")}</span>
                      <select
                        aria-label={t("csvEncoding")}
                        value={encoding}
                        onChange={(e) => setEncoding(e.target.value)}
                      >
                        {[
                          "UTF-8",
                          "Windows-1251",
                          "Windows-1250",
                          "UTF-16LE",
                          "UTF-16BE",
                        ].map((e) => (
                          <option key={e}>{e}</option>
                        ))}
                      </select>
                    </label>
                    <label>
                      <span>{t("csvDelimiter")}</span>
                      <select
                        aria-label={t("csvDelimiter")}
                        value={delimiter}
                        onChange={(e) => setDelimiter(e.target.value)}
                      >
                        {[",", ";", "\t", "|"].map((d) => (
                          <option value={d} key={d}>
                            {d === "\t" ? "Tab" : d}
                          </option>
                        ))}
                      </select>
                    </label>
                    <button onClick={() => void inspect(true)}>
                      {t("csvReread")}
                    </button>
                  </div>
                  <div className="table-wrap">
                    <table>
                      <thead>
                        <tr>
                          {preview.headers.map((h) => (
                            <th key={h}>{h}</th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {preview.sample.map((r, i) => (
                          <tr key={i}>
                            {preview.headers.map((h) => (
                              <td key={h} className="csv-text">
                                {r?.[h]}
                              </td>
                            ))}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  {preview.format === "bank" ? (
                    <>
                      <p className="warning">{t("csvBankTransfer")}</p>
                      <div className="filters">
                        {mapKeys.map((k) => (
                          <label key={k}>
                            <span>{t("csvMap_" + k)}</span>
                            <select
                              aria-label={t("csvMap_" + k)}
                              value={mapping[k] ?? ""}
                              onChange={(e) =>
                                setMapping({ ...mapping, [k]: e.target.value })
                              }
                            >
                              <option value="">—</option>
                              {preview.headers.map((h) => (
                                <option key={h}>{h}</option>
                              ))}
                            </select>
                          </label>
                        ))}
                      </div>
                      <div className="filters">
                        <label>
                          <span>{t("account")}</span>
                          <select
                            aria-label={t("account")}
                            required
                            value={account}
                            onChange={(e) => setAccount(e.target.value)}
                          >
                            <option value="">—</option>
                            {accounts.data?.data.items
                              .filter((a) => !a.archived_at)
                              .map((a) => (
                                <option key={a.id} value={a.id}>
                                  {a.name} · {a.currency_code}
                                </option>
                              ))}
                          </select>
                        </label>
                        {categorySelect(
                          "income",
                          incomeCategory,
                          setIncomeCategory,
                        )}
                        {categorySelect(
                          "expense",
                          expenseCategory,
                          setExpenseCategory,
                        )}
                        <label>
                          <span>{t("csvDateFormat")}</span>
                          <select
                            aria-label={t("csvDateFormat")}
                            value={dateFormat}
                            onChange={(e) => setDateFormat(e.target.value)}
                          >
                            {["Y-m-d", "d.m.Y", "d/m/Y", "m/d/Y"].map((f) => (
                              <option key={f}>{f}</option>
                            ))}
                          </select>
                        </label>
                        <label>
                          <span>{t("csvDecimal")}</span>
                          <select
                            aria-label={t("csvDecimal")}
                            value={decimal}
                            onChange={(e) => setDecimal(e.target.value)}
                          >
                            <option>.</option>
                            <option>,</option>
                          </select>
                        </label>
                        {mapping.direction && (
                          <>
                            <label>
                              <span>{t("csvIncomeValue")}</span>
                              <input
                                value={incomeValue}
                                onChange={(e) => setIncomeValue(e.target.value)}
                              />
                            </label>
                            <label>
                              <span>{t("csvExpenseValue")}</span>
                              <input
                                value={expenseValue}
                                onChange={(e) =>
                                  setExpenseValue(e.target.value)
                                }
                              />
                            </label>
                          </>
                        )}
                      </div>
                      {!mapping.direction && <p>{t("csvSignedAmounts")}</p>}
                    </>
                  ) : (
                    <>
                      <p>{t("csvOwnMapping")}</p>
                      <div className="filters">
                        {originalAccounts.map(({ id, name, currency_code }) => (
                          <label key={id}>
                            <span>
                              {name} · {currency_code}
                            </span>
                            <select
                              aria-label={`${name} · ${currency_code}`}
                              value={accountMappings[id] ?? id}
                              onChange={(e) =>
                                setAccountMappings({
                                  ...accountMappings,
                                  [id]: e.target.value,
                                })
                              }
                            >
                              <option value={id}>
                                {t("csvKeepId")} · {id}
                              </option>
                              {accounts.data?.data.items
                                .filter(
                                  (a) =>
                                    !a.archived_at &&
                                    a.id !== id &&
                                    a.currency_code === currency_code,
                                )
                                .map((a) => (
                                  <option key={a.id} value={a.id}>
                                    {a.name} · {a.currency_code}
                                  </option>
                                ))}
                            </select>
                          </label>
                        ))}
                      </div>
                    </>
                  )}
                  <label className="check">
                    <input
                      type="checkbox"
                      checked={confirmed}
                      onChange={(e) => setConfirmed(e.target.checked)}
                    />
                    {t("csvConfirmFormat")}
                  </label>
                  <button
                    className="primary"
                    disabled={
                      !confirmed ||
                      (preview.format === "bank" &&
                        (!account || !mapping.date || !mapping.amount))
                    }
                    onClick={() => void inspect()}
                  >
                    {t("csvPreview")}
                  </button>
                </fieldset>
              </>
            ) : (
              <>
                <p>{t("csvSelectionHint")}</p>
                <div className="inline">
                  <button
                    disabled={locked}
                    onClick={() =>
                      setSelected(
                        (preview.rows ?? [])
                          .filter(
                            (r) => !r.errors.length && !r.possible_duplicate,
                          )
                          .map((r) => r.number),
                      )
                    }
                  >
                    {t("csvSelectValid")}
                  </button>
                  <span>
                    {selected.length}/{preview.count}
                  </span>
                </div>
                <div className="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th>{t("csvInclude")}</th>
                        <th>#</th>
                        <th>{t("date")}</th>
                        <th>{t("kind")}</th>
                        <th>{t("amount")}</th>
                        <th>{t("description")}</th>
                        <th>{t("status")}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {preview.rows
                        ?.slice((page - 1) * 50, page * 50)
                        .map((r) => (
                          <tr key={r.number}>
                            <td>
                              <input
                                aria-label={`${t("csvInclude")} ${r.number}`}
                                type="checkbox"
                                disabled={locked || !!r.errors.length}
                                checked={selected.includes(r.number)}
                                onChange={(e) =>
                                  setSelected(
                                    e.target.checked
                                      ? [...selected, r.number]
                                      : selected.filter((n) => n !== r.number),
                                  )
                                }
                              />
                            </td>
                            <td>{r.number}</td>
                            <td>{r.operation?.occurred_on}</td>
                            <td>{r.operation ? t(r.operation.type) : "—"}</td>
                            <td>
                              {r.operation &&
                                formatMoney(
                                  r.operation.amount_minor,
                                  r.operation.currency_code,
                                  locale,
                                )}
                              {r.operation?.target_amount_minor &&
                                r.operation.target_currency_code &&
                                ` → ${formatMoney(r.operation.target_amount_minor, r.operation.target_currency_code, locale)}`}
                            </td>
                            <td className="csv-text">
                              {r.operation?.description}
                            </td>
                            <td>
                              {r.errors.map((c) => (
                                <p className="warning" key={c}>
                                  {t(c)}
                                </p>
                              ))}
                              {r.possible_duplicate && (
                                <span className="warning">
                                  {t("csvPossibleDuplicate")}
                                </span>
                              )}
                              {!r.errors.length &&
                                !r.possible_duplicate &&
                                t("csvValid")}
                            </td>
                          </tr>
                        ))}
                    </tbody>
                  </table>
                </div>
                <Pager
                  page={page}
                  pages={Math.max(1, Math.ceil(preview.count / 50))}
                  onPage={setPage}
                />
                <label className="check">
                  <input
                    type="checkbox"
                    disabled={locked}
                    checked={acceptDuplicates}
                    onChange={(e) => setAcceptDuplicates(e.target.checked)}
                  />
                  {t("csvAcceptDuplicates")}
                </label>
                <div className="inline">
                  <button
                    disabled={locked}
                    onClick={() => {
                      setPreview({ ...preview, needs_confirmation: true });
                      setConfirmed(false);
                    }}
                  >
                    {t("csvChangeMapping")}
                  </button>
                  <button
                    className="primary"
                    disabled={
                      !selected.length ||
                      mutation.isPending ||
                      (!acceptDuplicates &&
                        !!preview.rows?.some(
                          (r) =>
                            selected.includes(r.number) && r.possible_duplicate,
                        ))
                    }
                    onClick={() => void apply()}
                  >
                    {t(mutation.uncertain ? "retry" : "csvApply")} ·{" "}
                    {selected.length}
                  </button>
                </div>
                {mutation.uncertain && (
                  <p className="warning">{t("unknownResult")}</p>
                )}
              </>
            )}
          </>
        )}
      </section>
    </>
  );
}
