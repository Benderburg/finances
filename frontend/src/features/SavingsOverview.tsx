import { useQuery } from "@tanstack/react-query";
import { PiggyBank } from "lucide-react";
import { get } from "../data/api";
import { currencies, today } from "../domain/money";
import type { Dashboard, Session } from "../domain/types";
import { useT } from "../i18n";
import { State } from "../components/ui";
import { CurrencyBadge, MoneyAmount } from "../components/finance-ui";
import { ValuationNote } from "./FxViews";

export function SavingsOverview({ session }: { session: Session }) {
  const t = useT();
  const date = today(session.settings.timezone),
    currency = session.settings.base_currency_code;
  const q = useQuery({
    queryKey: ["dashboard", date.slice(0, 7), currency, date],
    queryFn: () =>
      get<Dashboard>(
        `/dashboard?month=${date.slice(0, 7)}&display_currency=${currency}&valuation_date=${date}`,
      ),
  });
  return (
    <State query={q}>
      {(d) => (
        <section className="panel savings-overview">
          <div className="savings-total">
            <span className="entity-icon">
              <PiggyBank size={24} />
            </span>
            <p>{t("savings")}</p>
            <MoneyAmount
              amount={d.consolidated.balances.known_subtotal.savings_minor}
              currency={currency}
              approximate
            />
            <small>
              {t(
                d.consolidated.balances.incomplete
                  ? "fxIncomplete"
                  : "estimateHint",
              )}
            </small>
          </div>
          <div className="currency-balances">
            {currencies.map((c) => (
              <div className="currency-balance" key={c}>
                <CurrencyBadge currency={c} />
                <MoneyAmount
                  amount={d.balances[c].savings_minor}
                  currency={c}
                />
              </div>
            ))}
          </div>
          <ValuationNote value={d.consolidated.balances} />
        </section>
      )}
    </State>
  );
}
