import { useEffect, useState } from "react";
import { useLocation } from "react-router-dom";
import { ApiError, request } from "../data/api";
import { ErrorMessage } from "../components/ui";
import { useT } from "../i18n";

export function VerificationPage({
  email,
  onRefresh,
  onLogout,
  logoutError,
}: {
  email: string;
  onRefresh: () => Promise<void>;
  onLogout: () => Promise<void>;
  logoutError: unknown;
}) {
  const t = useT();
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const [error, setError] = useState<unknown>(
    query.get("verification") === "delivery-failed"
      ? new ApiError("MAIL_DELIVERY_FAILED", 503)
      : undefined,
  );
  const [busy, setBusy] = useState(false);
  const [sent, setSent] = useState(false);
  const [remaining, setRemaining] = useState(0);
  useEffect(() => {
    if (
      new URLSearchParams(location.search).get("verification") ===
      "delivery-failed"
    ) {
      setError(new ApiError("MAIL_DELIVERY_FAILED", 503));
    }
  }, [location.search]);
  useEffect(() => {
    // A link can be opened in another tab or browser while this page stays open.
    const timer = window.setInterval(() => {
      if (!document.hidden) void onRefresh();
    }, 10000);
    return () => window.clearInterval(timer);
  }, [onRefresh]);
  useEffect(() => {
    if (remaining === 0) return;
    const timer = window.setTimeout(() => setRemaining(remaining - 1), 1000);
    return () => window.clearTimeout(timer);
  }, [remaining]);
  async function resend() {
    setBusy(true);
    setError(undefined);
    setSent(false);
    try {
      await request("/auth/resend-verification", "POST", {});
      setSent(true);
      setRemaining(60);
    } catch (e) {
      setError(e);
      if (e instanceof ApiError && e.code === "RATE_LIMITED") setRemaining(60);
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="verification">
      <span className="brand-mark">✦</span>
      <h1>{t("verify")}</h1>
      <p>{t("verificationInstructions")}</p>
      <p>
        <strong>{email}</strong>
      </p>
      <p className="hint">{t("verificationSpam")}</p>
      {query.get("verification") === "invalid" && (
        <p role="alert">{t("verificationInvalid")}</p>
      )}
      {sent && (
        <p className="success" role="status">
          {t("VERIFICATION_SENT")}
        </p>
      )}
      <ErrorMessage error={error ?? logoutError} />
      <button
        className="primary"
        disabled={busy || remaining > 0}
        onClick={() => void resend()}
      >
        {busy
          ? t("loading")
          : remaining > 0
            ? `${t("resend")} (${remaining})`
            : t("resend")}
      </button>
      <button onClick={() => void onRefresh()}>{t("verificationCheck")}</button>
      <button onClick={() => void onLogout()}>{t("logout")}</button>
    </main>
  );
}
