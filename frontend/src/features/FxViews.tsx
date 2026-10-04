import { useContext, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { get } from "../data/api";
import { currencies, formatMoney } from "../domain/money";
import type { Currency, Valuation } from "../domain/types";
import { LocaleContext, useT } from "../i18n";
import { ErrorMessage } from "../components/ui";

export function ValuationNote({ value }: { value: Valuation }) {
  const t = useT(),
    locale = useContext(LocaleContext);
  const fallbacks = value.meta.rates.filter((r) => r.fallback);
  const hasTotal = value.unconverted.some((r) => r.bucket === "total_minor");
  return (
    <>
      {value.incomplete && (
        <div className="warning" role="status">
          <strong>{t("fxIncomplete")}</strong>
          <p>{t("fxKnownPart")}</p>
          {value.missing.map((m, i) => (
            <small className="fx-line" key={i}>
              {m.source} → {m.target} · {m.requested_on}
            </small>
          ))}
          {value.unconverted
            .filter((r) => !hasTotal || r.bucket === "total_minor")
            .map((r, i) => (
              <small className="fx-line" key={i}>
                {r.date} ·{" "}
                {formatMoney(r.amount_minor, r.currency_code, locale)}
              </small>
            ))}
        </div>
      )}
      {!!fallbacks.length && (
        <p className="subtle">
          {t("fxFallback")}:{" "}
          {Array.from(
            new Set(
              fallbacks.map((r) => `${r.currency_code} ${r.effective_on}`),
            ),
          ).join(" · ")}
        </p>
      )}
      <details className="fx-provenance">
        <summary>{t("fxSource")}</summary>
        <p>{t("fxIndicative")}</p>
        {value.meta.rates.map((r, i) => (
          <small className="fx-line" key={i}>
            {r.provider} · {r.currency_code} · {r.effective_on} · v{r.version}
            {r.fallback ? ` (${r.requested_on})` : ""}
          </small>
        ))}
      </details>
    </>
  );
}

export function FxRefresh({
  date,
  dates = [],
}: {
  date: string;
  dates?: string[];
}) {
  const t = useT(),
    cache = useQueryClient();
  const [busy, setBusy] = useState(false),
    [error, setError] = useState<unknown>(),
    [status, setStatus] = useState("");
  const q = useQuery({
    queryKey: ["fx", date],
    queryFn: () =>
      get<{
        rates: {
          currency_code: Currency;
          rate: {
            mdl_per_unit: string;
            effective_on: string;
            fallback: boolean;
          } | null;
        }[];
      }>(`/fx/reference?date=${date}`),
  });
  async function refresh() {
    setBusy(true);
    setError(undefined);
    setStatus("");
    const list = Array.from(new Set([date, ...dates]))
      .sort()
      .slice(0, 32);
    try {
      let unavailable = false;
      for (let i = 0; i < list.length; i++) {
        setStatus(`${i + 1}/${list.length}`);
        const r = await get<{ fetch: { available?: boolean } }>(
          `/fx/reference?date=${list[i]}&refresh=1`,
        );
        unavailable ||= r.data.fetch.available === false;
      }
      setStatus(t(unavailable ? "RATE_PROVIDER_UNAVAILABLE" : "fxUpdated"));
      await cache.invalidateQueries();
    } catch (e) {
      setError(e);
    } finally {
      setBusy(false);
    }
  }
  return (
    <details className="panel fx-settings">
      <summary>
        {t("fxSource")} · {date}
      </summary>
      <p>{t("fxIndicative")}</p>
      <div className="inline">
        {currencies
          .filter((c) => c !== "MDL")
          .map((c) => {
            const rate = q.data?.data.rates.find(
              (r) => r.currency_code === c,
            )?.rate;
            return (
              <span key={c}>
                {c}:{" "}
                {rate
                  ? `${rate.mdl_per_unit.replace(/0+$/, "").replace(/\.$/, "")} MDL (${rate.effective_on})`
                  : t("fxMissing")}
              </span>
            );
          })}
      </div>
      <button
        disabled={busy || !navigator.onLine}
        onClick={() => void refresh()}
      >
        {busy ? t("loading") : t("fxRefresh")}
      </button>
      <span role="status"> {status}</span>
      <ErrorMessage error={error ?? q.error} />
    </details>
  );
}
