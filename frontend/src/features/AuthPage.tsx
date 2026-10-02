import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { request, csrf } from "../data/api";
import { clearSummaries } from "../data/offline";
import { useT } from "../i18n";
import { ErrorMessage } from "../components/ui";

export function AuthPage({
  onAuthenticated,
}: {
  onAuthenticated: () => Promise<void>;
}) {
  const t = useT(),
    location = useLocation(),
    navigate = useNavigate();
  const mode =
    location.pathname === "/register"
      ? "register"
      : location.pathname === "/forgot-password"
        ? "forgot"
        : location.pathname === "/reset-password"
          ? "reset"
          : "login";
  const [values, setValues] = useState<Record<string, string>>({}),
    [busy, setBusy] = useState(false),
    [error, setError] = useState<unknown>(),
    [message, setMessage] = useState("");
  const search = new URLSearchParams(location.search);
  const labels: Record<string, string> = {
    email: t("email"),
    password: t("password"),
    password_confirmation: t("passwordConfirm"),
    full_name: t("fullName"),
  };
  const fields =
    mode === "register"
      ? ["full_name", "email", "password", "password_confirmation"]
      : mode === "forgot"
        ? ["email"]
        : mode === "reset"
          ? ["password", "password_confirmation"]
          : ["email", "password"];
  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(undefined);
    try {
      await csrf();
      const payload =
        mode === "reset"
          ? {
              ...values,
              token: search.get("token") ?? "",
              email: search.get("email") ?? "",
            }
          : values;
      const r = await request<{ data: { message?: string } }>(
        "/auth/" +
          {
            login: "login",
            register: "register",
            forgot: "forgot-password",
            reset: "reset-password",
          }[mode],
        "POST",
        payload,
      );
      if (mode === "login" || mode === "register") {
        await clearSummaries();
        await onAuthenticated();
        navigate("/", { replace: true });
      } else setMessage(r.data.message ?? "saved");
    } catch (e) {
      setError(e);
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="auth-shell">
      <div className="auth-intro">
        <span className="brand-mark">✦</span>
        <h1>
          Norocel<span>2</span>
        </h1>
        <p>{t("overview")}</p>
        <div className="auth-decoration">
          <span>✦</span>
          <span>↗</span>
          <span>◌</span>
        </div>
      </div>
      <section className="auth-card">
        <h2>
          {t(
            mode === "forgot" ? "sendReset" : mode === "reset" ? "reset" : mode,
          )}
        </h2>
        <form onSubmit={submit}>
          <fieldset disabled={busy}>
            {fields.map((key) => (
              <label key={key}>
                <span>{labels[key]}</span>
                <input
                  type={
                    key.includes("password")
                      ? "password"
                      : key === "email"
                        ? "email"
                        : "text"
                  }
                  required
                  autoComplete={
                    key === "email"
                      ? "username"
                      : key === "password"
                        ? mode === "login"
                          ? "current-password"
                          : "new-password"
                        : key === "password_confirmation"
                          ? "new-password"
                          : "name"
                  }
                  minLength={
                    key.includes("password") && mode !== "login"
                      ? 12
                      : undefined
                  }
                  value={values[key] ?? ""}
                  onChange={(e) =>
                    setValues({ ...values, [key]: e.target.value })
                  }
                />
              </label>
            ))}
          </fieldset>
          {["register", "reset"].includes(mode) && (
            <p className="hint">{t("passwordHint")}</p>
          )}
          <ErrorMessage error={error} />
          {message && <p className="success">{t(message)}</p>}
          <button className="primary full" disabled={busy} type="submit">
            {busy
              ? t("loading")
              : t(
                  mode === "forgot"
                    ? "sendReset"
                    : mode === "reset"
                      ? "reset"
                      : mode,
                )}
          </button>
        </form>
        <div className="auth-links">
          <Link to={mode === "login" ? "/register" : "/login"}>
            {t(mode === "login" ? "register" : "login")}
          </Link>
          {mode === "login" && <Link to="/forgot-password">{t("forgot")}</Link>}
        </div>
      </section>
    </main>
  );
}
