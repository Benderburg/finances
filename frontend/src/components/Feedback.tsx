import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { Check, ShieldAlert, X } from "lucide-react";
import { useT } from "../i18n";
import { Modal } from "./ui";

type Confirm = (message: string) => Promise<boolean>;
const ConfirmationContext = createContext<Confirm>(() =>
  Promise.resolve(false),
);
export const useConfirm = () => useContext(ConfirmationContext);

export function FeedbackProvider({ children }: { children: ReactNode }) {
  const t = useT();
  const [confirmation, setConfirmation] = useState<string | null>(null);
  const resolve = useRef<((value: boolean) => void) | null>(null);
  const [toast, setToast] = useState(0);
  const confirm = useCallback<Confirm>(
    (message) =>
      new Promise((done) => {
        resolve.current?.(false);
        resolve.current = done;
        setConfirmation(message);
      }),
    [],
  );
  function finish(value: boolean) {
    resolve.current?.(value);
    resolve.current = null;
    setConfirmation(null);
  }
  useEffect(() => {
    const saved = () => setToast(Date.now());
    window.addEventListener("norocel-saved", saved);
    return () => {
      window.removeEventListener("norocel-saved", saved);
      resolve.current?.(false);
    };
  }, []);
  useEffect(() => {
    if (toast) {
      const timer = setTimeout(() => setToast(0), 4000);
      return () => clearTimeout(timer);
    }
  }, [toast]);
  return (
    <ConfirmationContext value={confirm}>
      {children}
      {confirmation && (
        <Modal title={t("confirmAction")} onClose={() => finish(false)}>
          <div className="confirmation-body">
            <span className="confirmation-icon">
              <ShieldAlert size={28} />
            </span>
            <p>{confirmation}</p>
          </div>
          <footer className="form-actions">
            <button autoFocus onClick={() => finish(false)}>
              {t("cancel")}
            </button>
            <button
              className="danger danger-filled"
              onClick={() => finish(true)}
            >
              {t("confirm")}
            </button>
          </footer>
        </Modal>
      )}
      {toast > 0 && (
        <div className="toast" role="status">
          <Check size={20} />
          <span>{t("saved")}</span>
          <button
            className="icon-button"
            aria-label={t("close")}
            onClick={() => setToast(0)}
          >
            <X size={18} />
          </button>
        </div>
      )}
    </ConfirmationContext>
  );
}
