import { Link } from "react-router-dom";
import { ChevronRight, PiggyBank } from "lucide-react";
import type { Account, Operation } from "../domain/types";
import { useT } from "../i18n";
import { Empty, Modal, State, useAllList } from "./ui";
import { MoneyAmount, operationActions } from "./finance-ui";

export function ActionPicker({
  savings,
  onSavings,
  onSelect,
  onClose,
}: {
  savings: boolean;
  onSavings: () => void;
  onSelect: (type: Operation["type"], toAccount?: string) => void;
  onClose: () => void;
}) {
  const t = useT();
  const accounts = useAllList<Account>("/accounts?kind=savings&archive=active");
  return (
    <Modal
      title={t(savings ? "chooseSavings" : "chooseAction")}
      onClose={onClose}
    >
      <p className="hint">{t(savings ? "savingsHint" : "chooseActionHint")}</p>
      {savings ? (
        <State query={accounts}>
          {(d) =>
            d.items.length ? (
              <div className="action-picker">
                {d.items.map((a) => (
                  <button key={a.id} onClick={() => onSelect("transfer", a.id)}>
                    <span className="action-picker-icon warm">
                      <PiggyBank size={24} />
                    </span>
                    <span>
                      <strong>{a.name}</strong>
                      <small>
                        <MoneyAmount
                          amount={a.balance_minor}
                          currency={a.currency_code}
                        />
                      </small>
                    </span>
                    <ChevronRight size={19} />
                  </button>
                ))}
              </div>
            ) : (
              <Empty
                title="emptySavings"
                hint="emptySavingsHint"
                action={
                  <Link
                    className="button primary"
                    to="/savings?new=1"
                    onClick={onClose}
                  >
                    {t("createAccount")}
                  </Link>
                }
              />
            )
          }
        </State>
      ) : (
        <div className="action-picker">
          {operationActions.map(({ key, icon: Icon, tone }) => (
            <button
              key={key}
              onClick={() => (key === "putAside" ? onSavings() : onSelect(key))}
            >
              <span className={`action-picker-icon ${tone}`}>
                <Icon size={24} />
              </span>
              <span>
                <strong>{t(key)}</strong>
                <small>{t(key + "Hint")}</small>
              </span>
              <ChevronRight size={19} />
            </button>
          ))}
        </div>
      )}
    </Modal>
  );
}
