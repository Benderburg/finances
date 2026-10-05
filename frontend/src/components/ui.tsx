import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseQueryResult,
} from "@tanstack/react-query";
import {
  X,
  AlertCircle,
  ChevronLeft,
  ChevronRight,
  Sparkles,
} from "lucide-react";
import { ApiError, command, get, getAll } from "../data/api";
import { useT } from "../i18n";
import { LogoLoader } from "./Brand";
import type { Envelope, List } from "../domain/types";
import { inputMoney, parseMoney } from "../domain/money";

export function useList<T>(path: string, enabled = true) {
  return useQuery({
    queryKey: ["list", path],
    queryFn: () => get<List<T>>(path),
    enabled,
  });
}
export function useAllList<T>(path: string) {
  return useQuery({
    queryKey: ["all-list", path],
    queryFn: () => getAll<T>(path),
  });
}
export function useCommand() {
  const cache = useQueryClient();
  const pending = useRef<{
    path: string;
    method: string;
    body: unknown;
    key: string;
  } | null>(null);
  const [uncertain, setUncertain] = useState(false);
  const mutation = useMutation({
    mutationFn: async (input: {
      path: string;
      method: string;
      body: unknown;
    }) => {
      pending.current ??= { ...input, key: crypto.randomUUID() };
      const p = pending.current;
      return command<unknown>(p.path, p.method, p.body, p.key);
    },
    onSuccess: async () => {
      pending.current = null;
      setUncertain(false);
      await cache.invalidateQueries();
      window.dispatchEvent(new Event("norocel-saved"));
    },
    onError: (e) => {
      if (e instanceof ApiError && (e.status === 0 || e.status >= 500))
        setUncertain(true);
      else {
        pending.current = null;
        setUncertain(false);
      }
    },
  });
  return {
    ...mutation,
    uncertain,
    submit: (path: string, method: string, body: unknown) =>
      mutation.mutateAsync({ path, method, body }),
  };
}
export function ErrorMessage({ error }: { error: unknown }) {
  const t = useT();
  if (!error) return null;
  const code =
    error instanceof ApiError
      ? error.code
      : error instanceof Error
        ? error.message
        : "REQUEST_FAILED";
  return (
    <div className="error" role="alert">
      <AlertCircle size={18} />
      <div>
        {t(code)}
        {error instanceof ApiError &&
          Object.keys(error.fields ?? {}).map((field) => (
            <div className="field-error" key={field}>
              {t(
                (
                  {
                    full_name: "fullName",
                    amount_minor: "amount",
                    opening_balance_minor: "opening",
                    target_amount_minor: "target",
                    principal_minor: "amount",
                    limit_minor: "limit",
                    password_confirmation: "passwordConfirm",
                    current_password: "currentPassword",
                    account_id: "account",
                    category_id: "category",
                    currency_code: "currency",
                    period_month: "month",
                  } as Record<string, string>
                )[field] ?? field,
              )}
              : {t("validationField")}
            </div>
          ))}
      </div>
    </div>
  );
}
export function State<T>({
  query,
  children,
}: {
  query: UseQueryResult<Envelope<T>, Error>;
  children: (data: T) => ReactNode;
}) {
  const t = useT();
  if (query.isPending) return <LogoLoader />;
  if (query.isError)
    return (
      <div className="state">
        <ErrorMessage error={query.error} />
        <button onClick={() => void query.refetch()}>{t("retry")}</button>
      </div>
    );
  return <>{children(query.data.data)}</>;
}
export function Empty({
  action,
  title = "empty",
  hint,
}: {
  action?: ReactNode;
  title?: string;
  hint?: string;
}) {
  const t = useT();
  return (
    <div className="empty">
      <span className="empty-icon">
        <Sparkles size={28} strokeWidth={1.5} />
      </span>
      <h3>{t(title)}</h3>
      {hint && <p>{t(hint)}</p>}
      {action}
    </div>
  );
}
export function Pager({
  page,
  pages,
  onPage,
}: {
  page: number;
  pages: number;
  onPage: (n: number) => void;
}) {
  const t = useT();
  return (
    <div className="pager">
      <button
        aria-label={t("previous")}
        disabled={page <= 1}
        onClick={() => onPage(page - 1)}
      >
        <ChevronLeft size={18} />
      </button>
      <span>
        {t("page")} {page} / {pages}
      </span>
      <button
        aria-label={t("next")}
        disabled={page >= pages}
        onClick={() => onPage(page + 1)}
      >
        <ChevronRight size={18} />
      </button>
    </div>
  );
}
export function Modal({
  title,
  children,
  onClose,
}: {
  title: string;
  children: ReactNode;
  onClose: () => void;
}) {
  const t = useT();
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  useEffect(() => {
    const dialog = ref.current;
    const open = () =>
      requestAnimationFrame(() => {
        if (dialog?.isConnected && !dialog.open && navigator.onLine)
          dialog.showModal();
      });
    const close = () => dialog?.close();
    open();
    window.addEventListener("online", open);
    window.addEventListener("offline", close);
    return () => {
      window.removeEventListener("online", open);
      window.removeEventListener("offline", close);
      close();
    };
  }, []);
  return (
    <dialog
      ref={ref}
      aria-labelledby={titleId}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
    >
      <div className="modal-title">
        <h2 id={titleId}>{title}</h2>
        <button
          className="icon-button"
          aria-label={t("close")}
          onClick={onClose}
        >
          <X size={22} />
        </button>
      </div>
      {children}
    </dialog>
  );
}
export interface Field {
  key: string;
  label: string;
  type?:
    | "text"
    | "money"
    | "select"
    | "date"
    | "month"
    | "checkbox"
    | "password"
    | "email"
    | "textarea";
  options?: { value: string; label: string }[];
  required?: boolean;
  initial?: string | boolean;
  nullable?: boolean;
  allowZero?: boolean;
  maxLength?: number;
}
export function Editor({
  title,
  path,
  method = "POST",
  fields,
  revision,
  onClose,
  onSaved,
  hint,
}: {
  title: string;
  path: string;
  method?: string;
  fields: Field[];
  revision?: number;
  onClose: () => void;
  onSaved?: (data: unknown) => void;
  hint?: ReactNode;
}) {
  const t = useT();
  const mutation = useCommand();
  const [values, setValues] = useState<Record<string, string | boolean>>(() =>
    Object.fromEntries(
      fields.map((f) => [
        f.key,
        f.initial ?? (f.type === "checkbox" ? false : ""),
      ]),
    ),
  );
  const [localError, setError] = useState<unknown>();
  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(undefined);
    try {
      const body: Record<string, unknown> = revision
        ? { expected_revision: revision }
        : {};
      for (const f of fields) {
        let value = values[f.key];
        if (f.type === "money") value = parseMoney(String(value), f.allowZero);
        if (f.type === "month") value = String(value) + "-01";
        if (value === "" && f.nullable) value = null as unknown as string;
        if (value !== "" || f.nullable || f.required || f.type === "checkbox")
          body[f.key] = value;
      }
      const result = await mutation.submit(path, method, body);
      onSaved?.(result.data);
      onClose();
    } catch (e) {
      setError(e);
    }
  }
  const close = () => {
    if (!mutation.isPending && !mutation.uncertain) onClose();
  };
  return (
    <Modal title={title} onClose={close}>
      <form onSubmit={submit}>
        {hint && <p className="hint">{hint}</p>}
        <fieldset disabled={mutation.isPending || mutation.uncertain}>
          {fields.map((f) => (
            <label key={f.key} className={f.type === "checkbox" ? "check" : ""}>
              {f.type !== "checkbox" && <span>{f.label}</span>}
              {f.type === "select" ? (
                <select
                  aria-label={f.label}
                  required={f.required}
                  value={String(values[f.key])}
                  onChange={(e) =>
                    setValues({ ...values, [f.key]: e.target.value })
                  }
                >
                  <option value="">—</option>
                  {f.options?.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              ) : f.type === "textarea" ? (
                <textarea
                  maxLength={f.maxLength ?? 2000}
                  value={String(values[f.key])}
                  onChange={(e) =>
                    setValues({ ...values, [f.key]: e.target.value })
                  }
                />
              ) : (
                <input
                  type={
                    f.type === "checkbox"
                      ? "checkbox"
                      : f.type === "money"
                        ? "text"
                        : (f.type ?? "text")
                  }
                  inputMode={f.type === "money" ? "decimal" : undefined}
                  required={f.required}
                  maxLength={f.maxLength ?? 255}
                  checked={
                    f.type === "checkbox" ? Boolean(values[f.key]) : undefined
                  }
                  value={
                    f.type === "checkbox" ? undefined : String(values[f.key])
                  }
                  onChange={(e) =>
                    setValues({
                      ...values,
                      [f.key]:
                        f.type === "checkbox"
                          ? e.target.checked
                          : e.target.value,
                    })
                  }
                />
              )}{" "}
              {f.type === "checkbox" && <span>{f.label}</span>}
            </label>
          ))}
        </fieldset>
        <ErrorMessage error={localError ?? mutation.error} />
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
            disabled={mutation.isPending}
            type="submit"
          >
            {mutation.isPending
              ? t("loading")
              : mutation.uncertain
                ? t("retry")
                : t("save")}
          </button>
        </footer>
      </form>
    </Modal>
  );
}
export const moneyField = (
  key: string,
  label: string,
  value?: string,
  allowZero = false,
): Field => ({
  key,
  label,
  type: "money",
  required: true,
  initial: value ? inputMoney(value) : "",
  allowZero,
});
export function Progress({ value }: { value: string }) {
  const t = useT();
  const percent = Math.max(0, Math.min(100, Number(value)));
  return (
    <div
      className="progress"
      role="progressbar"
      aria-label={t("progressLabel")}
      aria-valuenow={percent}
      aria-valuemin={0}
      aria-valuemax={100}
    >
      <span style={{ width: percent + "%" }} />
    </div>
  );
}
