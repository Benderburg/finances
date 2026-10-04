import { useConfirm } from "../components/Feedback";
import { useContext, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { request, download } from "../data/api";
import { clearSummaries } from "../data/offline";
import type { Category, Preview, Session } from "../domain/types";
import { currencies, formatMoney, today } from "../domain/money";
import { LocaleContext, categoryName, useT } from "../i18n";
import {
  Editor,
  Empty,
  ErrorMessage,
  Pager,
  State,
  useCommand,
  useList,
  type Field,
} from "../components/ui";

export function CategoriesPage() {
  const confirm = useConfirm();
  const t = useT(),
    mutation = useCommand();
  const [page, setPage] = useState(1),
    [editor, setEditor] = useState<Category | "new" | null>(null);
  const q = useList<Category>("/categories?page=" + page);
  return (
    <>
      <div className="page-heading">
        <h1>{t("categories")}</h1>
        <button className="primary" onClick={() => setEditor("new")}>
          {t("new")}
        </button>
      </div>
      <ErrorMessage error={mutation.error} />
      <section className="panel">
        <State query={q}>
          {(d) => (
            <>
              {d.items.length ? (
                d.items.map((c) => (
                  <div className="summary-line" key={c.id}>
                    <span>
                      {categoryName(c, t)}{" "}
                      <small>
                        · {t(c.kind)}{" "}
                        {c.archived_at ? " · " + t("archived") : ""}
                      </small>
                    </span>
                    {c.system_code !== "goal_expense" && (
                      <div className="inline">
                        <button onClick={() => setEditor(c)}>
                          {t("edit")}
                        </button>
                        <button
                          onClick={() =>
                            void mutation
                              .submit(
                                `/categories/${c.id}/${c.archived_at ? "unarchive" : "archive"}`,
                                "POST",
                                { expected_revision: c.revision },
                              )
                              .catch(() => {})
                          }
                        >
                          {t(c.archived_at ? "unarchive" : "archive")}
                        </button>
                        {!c.is_system && (
                          <button
                            onClick={async () => {
                              if (
                                await confirm(
                                  t("delete") + " " + categoryName(c, t) + "?",
                                )
                              )
                                void mutation
                                  .submit("/categories/" + c.id, "DELETE", {
                                    expected_revision: c.revision,
                                  })
                                  .catch(() => {});
                            }}
                          >
                            {t("delete")}
                          </button>
                        )}
                      </div>
                    )}
                  </div>
                ))
              ) : (
                <Empty />
              )}
              <Pager page={page} pages={d.pagination.pages} onPage={setPage} />
            </>
          )}
        </State>
      </section>
      {editor && (
        <Editor
          title={t(editor === "new" ? "new" : "edit") + " " + t("category")}
          path={"/categories" + (editor === "new" ? "" : "/" + editor.id)}
          method={editor === "new" ? "POST" : "PATCH"}
          revision={editor === "new" ? undefined : editor.revision}
          fields={[
            {
              key: "name",
              label: t("name"),
              required: true,
              initial: editor === "new" ? "" : categoryName(editor, t),
            },
            ...(editor === "new"
              ? [
                  {
                    key: "kind",
                    label: t("kind"),
                    type: "select",
                    required: true,
                    initial: "expense",
                    options: [
                      { value: "expense", label: t("expense") },
                      { value: "income", label: t("income") },
                    ],
                  } as Field,
                ]
              : []),
          ]}
          onClose={() => setEditor(null)}
        />
      )}
    </>
  );
}
export function SettingsPage({
  session,
  onLogout,
}: {
  session: Session;
  onLogout: () => void;
}) {
  const confirm = useConfirm();
  const t = useT(),
    locale = useContext(LocaleContext),
    cache = useQueryClient(),
    mutation = useCommand();
  const [edit, setEdit] = useState(false),
    [authForm, setAuthForm] = useState<"password" | "email" | null>(null),
    [file, setFile] = useState<File | null>(null),
    [preview, setPreview] = useState<Preview | null>(null),
    [error, setError] = useState<unknown>(),
    [checking, setChecking] = useState(false),
    [legacyMonth, setMonth] = useState(
      today(session.settings.timezone).slice(0, 7),
    ),
    [backupId, setBackupId] = useState<string | null>(null),
    [offlineOptIn, setOffline] = useState(
      localStorage.getItem("norocel-summary-opt-in:" + session.user.id) === "1",
    );
  async function checkFile() {
    if (!file) return;
    setChecking(true);
    setError(undefined);
    try {
      const body = new FormData();
      body.append("file", file);
      body.append("legacy_month", legacyMonth);
      const r = await request<{ data: Preview }>(
        "/api/v1/backup/preview",
        "POST",
        body,
      );
      setPreview(r.data);
    } catch (e) {
      setError(e);
    } finally {
      setChecking(false);
    }
  }
  const fields: Field[] = [
    {
      key: "full_name",
      label: t("fullName"),
      required: true,
      initial: session.user.full_name,
    },
    {
      key: "avatar_url",
      label: t("avatar"),
      nullable: true,
      initial: session.user.avatar_url ?? "",
      maxLength: 2048,
    },
    {
      key: "locale",
      label: t("locale"),
      type: "select",
      required: true,
      initial: session.settings.locale,
      options: [
        { value: "ro", label: "Română" },
        { value: "ru", label: "Русский" },
        { value: "en", label: "English" },
      ],
    },
    {
      key: "base_currency_code",
      label: t("baseCurrency"),
      type: "select",
      required: true,
      initial: session.settings.base_currency_code,
      options: currencies.map((c) => ({ value: c, label: c })),
    },
    {
      key: "theme",
      label: t("theme"),
      type: "select",
      required: true,
      initial: session.settings.theme,
      options: ["light", "dark", "system"].map((k) => ({
        value: k,
        label: t(k),
      })),
    },
    {
      key: "timezone",
      label: t("timezone"),
      required: true,
      initial: session.settings.timezone,
    },
  ];
  return (
    <>
      <div className="page-heading">
        <h1>{t("settings")}</h1>
        <button onClick={onLogout}>{t("logout")}</button>
      </div>
      <section className="panel profile-panel">
        <div className="profile-avatar">
          {session.user.full_name.slice(0, 1).toUpperCase()}
        </div>
        <div className="card-top">
          <h2>{session.user.full_name}</h2>
          <button onClick={() => setEdit(true)}>{t("edit")}</button>
        </div>
        <p>{session.user.email}</p>
        {session.pending_email && (
          <p className="warning">
            {t("pendingEmail")}: {session.pending_email}
          </p>
        )}
        <p className="subtle">
          {session.settings.timezone} · {session.settings.base_currency_code} ·{" "}
          {session.settings.locale.toUpperCase()}
        </p>
        <div className="inline wrap">
          <button onClick={() => setAuthForm("password")}>
            {t("changePassword")}
          </button>
          <button onClick={() => setAuthForm("email")}>
            {t("changeEmail")}
          </button>
        </div>
      </section>
      <section className="panel">
        <h2>
          JSON · {t("export")} / {t("import")}
        </h2>
        <button onClick={() => download("/api/v1/backup/export")}>
          {t("export")}
        </button>
        <label>
          <span>{t("import")}</span>
          <input
            type="file"
            accept="application/json,.json"
            onChange={(e) => {
              setFile(e.target.files?.[0] ?? null);
              setPreview(null);
            }}
          />
        </label>
        <label>
          <span>
            {t("month")} · {t("legacy")}
          </span>
          <input
            type="month"
            value={legacyMonth}
            onChange={(e) => {
              setMonth(e.target.value);
              setPreview(null);
            }}
          />
        </label>
        <button disabled={!file || checking} onClick={() => void checkFile()}>
          {checking ? t("loading") : t("preview")}
        </button>
        <ErrorMessage error={error ?? mutation.error} />
        {preview && (
          <div className="restore-preview">
            <h3>{t("preview")}</h3>
            {Object.entries(preview.counts)
              .filter(([key]) =>
                [
                  "accounts",
                  "categories",
                  "operations",
                  "goals",
                  "budgets",
                  "liabilities",
                ].includes(key),
              )
              .map(([key, count]) => (
                <div className="summary-line" key={key}>
                  <span>{t(key)}</span>
                  <strong>{count}</strong>
                </div>
              ))}
            {preview.balances.map((b) => (
              <p key={b.account_id}>
                {b.name} ·{" "}
                {formatMoney(b.balance_minor, b.currency_code, locale)}
              </p>
            ))}
            {preview.warnings.map((w, i) => (
              <p className="warning" key={i}>
                {t(w.code)}
                {w.key ? " · " + w.key : ""}
                {w.month ? " · " + w.month : ""}
                {w.currency_code ? " · " + w.currency_code : ""}
                {w.original_rate
                  ? " · " + w.original_rate + " → " + w.effective_rate
                  : ""}
              </p>
            ))}
            <p className="warning">{t("restoreWarning")}</p>
            <button
              className="danger"
              disabled={mutation.isPending}
              onClick={async () => {
                if (await confirm(t("restoreWarning")))
                  void mutation
                    .submit("/backup/apply", "POST", {
                      preview_token: preview.preview_token,
                      expected_workspace_revision:
                        preview.expected_workspace_revision,
                    })
                    .then((r) => {
                      setBackupId(
                        (r.data as { previous_backup_id: string })
                          .previous_backup_id,
                      );
                      setPreview(null);
                      void clearSummaries();
                    })
                    .catch(() => {});
              }}
            >
              {t("restore")}
            </button>
          </div>
        )}
        {backupId && (
          <button
            onClick={() => download("/api/v1/backup/previous/" + backupId)}
          >
            {t("previousBackup")}
          </button>
        )}
      </section>
      <section className="panel">
        <h2>{t("offline")}</h2>
        <label className="check">
          <input
            type="checkbox"
            checked={offlineOptIn}
            onChange={(e) => {
              setOffline(e.target.checked);
              localStorage.setItem(
                "norocel-summary-opt-in:" + session.user.id,
                e.target.checked ? "1" : "0",
              );
              if (!e.target.checked) void clearSummaries();
              else void cache.invalidateQueries({ queryKey: ["dashboard"] });
            }}
          />
          {t("offlineOptIn")}
        </label>
        <button onClick={() => void clearSummaries()}>
          {t("clearOffline")}
        </button>
      </section>
      {edit && (
        <Editor
          title={t("settings")}
          path="/me"
          method="PATCH"
          fields={fields}
          onClose={() => setEdit(false)}
        />
      )}{" "}
      {authForm && (
        <AuthChange mode={authForm} onClose={() => setAuthForm(null)} />
      )}
    </>
  );
}
function AuthChange({
  mode,
  onClose,
}: {
  mode: "password" | "email";
  onClose: () => void;
}) {
  const t = useT(),
    cache = useQueryClient();
  const [values, setValues] = useState<Record<string, string>>({}),
    [busy, setBusy] = useState(false),
    [error, setError] = useState<unknown>(),
    [message, setMessage] = useState("");
  const keys =
    mode === "password"
      ? ["current_password", "password", "password_confirmation"]
      : ["current_password", "email"];
  const labels: Record<string, string> = {
    current_password: t("currentPassword"),
    password: t("password"),
    password_confirmation: t("passwordConfirm"),
    email: t("email"),
  };
  return (
    <section className="panel">
      <h2>{t(mode === "password" ? "changePassword" : "changeEmail")}</h2>
      <form
        onSubmit={async (e) => {
          e.preventDefault();
          setBusy(true);
          try {
            const r = await request<{ data: { message: string } }>(
              "/auth/" +
                (mode === "password" ? "change-password" : "email-change"),
              "POST",
              values,
            );
            setMessage(r.data.message);
            await cache.invalidateQueries({ queryKey: ["me"] });
          } catch (e) {
            setError(e);
          } finally {
            setBusy(false);
          }
        }}
      >
        {keys.map((key) => (
          <label key={key}>
            <span>{labels[key]}</span>
            <input
              required
              type={key === "email" ? "email" : "password"}
              value={values[key] ?? ""}
              onChange={(e) => setValues({ ...values, [key]: e.target.value })}
            />
          </label>
        ))}
        <ErrorMessage error={error} />
        {message && <p className="success">{t(message)}</p>}
        <div className="form-actions">
          <button type="button" onClick={onClose}>
            {t("close")}
          </button>
          <button className="primary" disabled={busy}>
            {t("save")}
          </button>
        </div>
      </form>
    </section>
  );
}
