import { useContext, useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, NavLink, Route, Routes, useNavigate } from "react-router-dom";
import {
  Home,
  ArrowLeftRight,
  Wallet,
  Target,
  ChartNoAxesCombined,
  Settings,
  Plus,
  Ellipsis,
  Tags,
  HandCoins,
  Shield,
  LogOut,
  CalendarRange,
} from "lucide-react";
import type { Dashboard, Locale, Operation, Session } from "./domain/types";
import { ApiError, csrf, get, request } from "./data/api";
import {
  clearSummaries,
  loadSummary,
  saveSummary,
  type Summary,
} from "./data/offline";
import { currencies, formatMoney } from "./domain/money";
import { LocaleContext, translate, useT } from "./i18n";
import { ErrorMessage } from "./components/ui";
import {
  AccountsPage,
  BudgetsPage,
  DashboardPage,
  GoalsPage,
  LiabilitiesPage,
  OperationDetails,
  OperationsPage,
} from "./features/FinancePages";
import { OperationForm } from "./features/OperationForm";
import { AuthPage } from "./features/AuthPage";
import { CategoriesPage, SettingsPage } from "./features/SettingsPage";
import { ReportsPage } from "./features/ReportsPage";
import { AdminPage } from "./features/AdminPage";
import { CsvPage } from "./features/CsvPage";

function useOnline() {
  const [online, setOnline] = useState(navigator.onLine);
  useEffect(() => {
    const update = () => setOnline(navigator.onLine);
    window.addEventListener("online", update);
    window.addEventListener("offline", update);
    return () => {
      window.removeEventListener("online", update);
      window.removeEventListener("offline", update);
    };
  }, []);
  return online;
}
export function App() {
  const online = useOnline(),
    cache = useQueryClient(),
    navigate = useNavigate();
  const [pending, setPending] = useState(
      localStorage.getItem("norocel-pending-logout") === "1",
    ),
    [logoutError, setLogoutError] = useState<unknown>(),
    [publicLocale, setLocale] = useState<Locale>(
      (localStorage.getItem("norocel-locale") as Locale) || "ro",
    );
  const me = useQuery({
    queryKey: ["me"],
    queryFn: () => get<Session>("/me"),
    enabled: online && !pending,
    retry: false,
  });
  useEffect(() => {
    const expired = () => {
      void me.refetch();
    };
    window.addEventListener("norocel-session-expired", expired);
    return () => window.removeEventListener("norocel-session-expired", expired);
  }, [me.refetch]);
  useEffect(() => {
    if (me.isError && me.error instanceof ApiError && me.error.status === 401) {
      cache.removeQueries({ predicate: (q) => q.queryKey[0] !== "me" });
      void clearSummaries();
    }
  }, [me.isError, me.error, cache]);
  useEffect(() => {
    if (online && pending) {
      void csrf()
        .then(() => request("/auth/logout", "POST", {}))
        .then(() => {
          localStorage.removeItem("norocel-pending-logout");
          setPending(false);
          cache.clear();
          navigate("/login", { replace: true });
          void me.refetch();
        })
        .catch((error) => {
          if (error instanceof ApiError && error.status === 401) {
            localStorage.removeItem("norocel-pending-logout");
            setPending(false);
            cache.clear();
            navigate("/login", { replace: true });
            void me.refetch();
          } else setLogoutError(error);
        });
    }
  }, [online, pending]);
  useEffect(() => {
    if (me.data) {
      const s = me.data.data;
      localStorage.setItem("norocel-locale", s.settings.locale);
      localStorage.setItem("norocel-theme", s.settings.theme);
      document.documentElement.lang = s.settings.locale;
      document.documentElement.dataset.theme = s.settings.theme;
      const old = localStorage.getItem("norocel-summary-owner");
      if (old && old !== s.user.id) void clearSummaries();
      localStorage.setItem("norocel-summary-owner", s.user.id);
    }
  }, [me.data]);
  useEffect(
    () =>
      cache.getQueryCache().subscribe((event) => {
        if (event.type !== "updated" || event.action.type !== "success") return;
        const q = event.query;
        if (q.queryKey[0] === "dashboard" && me.data && !me.isError) {
          const s = me.data.data;
          if (
            localStorage.getItem("norocel-summary-opt-in:" + s.user.id) === "1"
          ) {
            const result = q.state.data as {
              data: Dashboard;
              meta: { workspace_revision: number };
            };
            void saveSummary(
              s.user.id,
              result.meta.workspace_revision,
              result.data,
            ).catch(() => {});
          }
        }
      }),
    [cache, me.data, me.isError],
  );
  async function logout() {
    setLogoutError(undefined);
    setPending(true);
    localStorage.setItem("norocel-pending-logout", "1");
    cache.clear();
    await clearSummaries();
    navigate("/login", { replace: true });
  }
  const unauthorized =
    me.isError && me.error instanceof ApiError && me.error.status === 401;
  const session = unauthorized ? undefined : me.data?.data;
  const locale = session?.settings.locale ?? publicLocale;
  async function authenticated() {
    cache.clear();
    await me.refetch();
  }
  return (
    <LocaleContext value={locale}>
      {session?.user.email_verified_at && !pending && (
        <Workspace
          session={session}
          online={online}
          onLogout={() => void logout()}
        />
      )}
      {!online ? (
        pending ? (
          <main className="offline-screen">
            <h1>Norocel · {translate(locale, "offline")}</h1>
            <p>{translate(locale, "logoutPending")}</p>
          </main>
        ) : (
          <Offline onLogout={() => void logout()} />
        )
      ) : pending ? (
        <main className="state">
          <ErrorMessage error={logoutError} />
          <p>{translate(locale, "logout")}</p>
          {Boolean(logoutError) && (
            <button
              onClick={() => {
                setPending(false);
                setTimeout(() => setPending(true), 0);
              }}
            >
              {translate(locale, "retry")}
            </button>
          )}
        </main>
      ) : me.isPending ? (
        <main className="state">{translate(locale, "loading")}</main>
      ) : !session ? (
        <>
          <div className="public-locale">
            <select
              aria-label="Language"
              value={publicLocale}
              onChange={(e) => {
                setLocale(e.target.value as Locale);
                localStorage.setItem("norocel-locale", e.target.value);
              }}
            >
              <option value="ro">Română</option>
              <option value="ru">Русский</option>
              <option value="en">English</option>
            </select>
          </div>
          {me.error instanceof ApiError && me.error.status !== 401 && (
            <ErrorMessage error={me.error} />
          )}
          <AuthPage key={location.pathname} onAuthenticated={authenticated} />
        </>
      ) : !session.user.email_verified_at ? (
        <main className="verification">
          <span className="brand-mark">✦</span>
          <h1>{translate(locale, "verify")}</h1>
          <p>{session.user.email}</p>
          <button
            className="primary"
            onClick={() =>
              void request("/auth/resend-verification", "POST", {}).catch(
                setLogoutError,
              )
            }
          >
            {translate(locale, "resend")}
          </button>
          <button onClick={() => void me.refetch()}>
            {translate(locale, "refresh")}
          </button>
          <button onClick={() => void logout()}>
            {translate(locale, "logout")}
          </button>
          <ErrorMessage error={logoutError} />
        </main>
      ) : null}
    </LocaleContext>
  );
}
const navigation = [
  { path: "/", key: "home", icon: Home },
  { path: "/operations", key: "operations", icon: ArrowLeftRight },
  { path: "/accounts", key: "accounts", icon: Wallet },
  { path: "/goals", key: "goals", icon: Target },
  { path: "/budgets", key: "budgets", icon: CalendarRange },
  { path: "/liabilities", key: "liabilities", icon: HandCoins },
  { path: "/reports", key: "reports", icon: ChartNoAxesCombined },
  { path: "/categories", key: "categories", icon: Tags },
  { path: "/csv", key: "csvTitle", icon: ArrowLeftRight },
  { path: "/settings", key: "settings", icon: Settings },
];
function Workspace({
  session,
  online,
  onLogout,
}: {
  session: Session;
  online: boolean;
  onLogout: () => void;
}) {
  const t = useT();
  const [form, setForm] = useState<{
      operation?: Operation;
      toAccount?: string;
      initialType?: Operation["type"];
    } | null>(null),
    [detail, setDetail] = useState<Operation | null>(null),
    [update, setUpdate] = useState<ServiceWorker | null>(null);
  const open = (o?: Operation, to?: string, type?: Operation["type"]) =>
    o ? setDetail(o) : setForm({ toAccount: to, initialType: type });
  useEffect(() => {
    if (!("serviceWorker" in navigator) || import.meta.env.DEV) return;
    void navigator.serviceWorker
      .register("/sw.js")
      .then((r) => {
        if (r.waiting) setUpdate(r.waiting);
        r.addEventListener("updatefound", () => {
          const worker = r.installing;
          worker?.addEventListener("statechange", () => {
            if (
              worker.state === "installed" &&
              navigator.serviceWorker.controller
            )
              setUpdate(worker);
          });
        });
      })
      .catch(() => {});
  }, []);
  return (
    <div
      className="app-shell"
      hidden={!online}
      style={!online ? { display: "none" } : undefined}
    >
      <aside className="sidebar">
        <Link className="brand" to="/">
          <span className="brand-mark">✦</span>Norocel<sup>2</sup>
        </Link>
        <nav>
          {navigation.map(({ path, key, icon: Icon }) => (
            <NavLink key={key} to={path} end={path === "/"}>
              <Icon size={19} />
              {t(key)}
            </NavLink>
          ))}
          {session.user.is_admin && (
            <NavLink to="/admin">
              <Shield size={19} />
              {t("admin")}
            </NavLink>
          )}
        </nav>
        <div className="sidebar-bottom">
          <div className="user-avatar">
            {session.user.full_name.slice(0, 1).toUpperCase()}
          </div>
          <div>
            <strong>{session.user.full_name}</strong>
            <small>{session.user.billing_plan}</small>
          </div>
          <button
            className="icon-button"
            onClick={onLogout}
            aria-label={t("logout")}
          >
            <LogOut size={18} />
          </button>
        </div>
      </aside>
      <div className="workspace">
        <header className="topbar">
          <Link className="mobile-brand" to="/">
            ✦ Norocel
          </Link>
          <span className="desktop-greeting">{t("neutralHint")}</span>
          <div className="inline">
            <span className="live-dot" />
            <span>{session.settings.base_currency_code}</span>
            <button className="primary" onClick={() => setForm({})}>
              <Plus size={18} />
              <span>{t("newOperation")}</span>
            </button>
          </div>
        </header>
        {update && (
          <div className="update-banner">
            {t("updateReady")}
            <button
              onClick={() => {
                update.postMessage({ type: "SKIP_WAITING" });
                navigator.serviceWorker.addEventListener(
                  "controllerchange",
                  () => location.reload(),
                  { once: true },
                );
              }}
            >
              {t("restart")}
            </button>
          </div>
        )}
        <main className="page-content">
          <Routes>
            <Route
              path="/"
              element={<DashboardPage session={session} openOperation={open} />}
            />
            <Route
              path="/operations"
              element={<OperationsPage openOperation={open} />}
            />
            <Route
              path="/accounts"
              element={<AccountsPage session={session} openOperation={open} />}
            />
            <Route path="/goals" element={<GoalsPage session={session} />} />
            <Route
              path="/liabilities"
              element={<LiabilitiesPage session={session} />}
            />
            <Route
              path="/budgets"
              element={<BudgetsPage session={session} />}
            />
            <Route
              path="/reports"
              element={<ReportsPage session={session} />}
            />
            <Route path="/categories" element={<CategoriesPage />} />
            <Route path="/csv" element={<CsvPage />} />
            <Route
              path="/settings"
              element={<SettingsPage session={session} onLogout={onLogout} />}
            />
            <Route
              path="/more"
              element={
                <div className="more-menu">
                  {navigation.slice(3).map(({ path, key, icon: Icon }) => (
                    <Link key={key} to={path}>
                      <Icon />
                      {t(key)}
                    </Link>
                  ))}
                  {session.user.is_admin && (
                    <Link to="/admin">
                      <Shield />
                      {t("admin")}
                    </Link>
                  )}
                </div>
              }
            />
            {session.user.is_admin && (
              <Route path="/admin" element={<AdminPage />} />
            )}
            <Route
              path="*"
              element={<DashboardPage session={session} openOperation={open} />}
            />
          </Routes>
        </main>
      </div>
      <nav className="bottom-nav">
        <NavLink to="/" end>
          <Home size={21} />
          {t("home")}
        </NavLink>
        <NavLink to="/operations">
          <ArrowLeftRight size={21} />
          {t("operations")}
        </NavLink>
        <button
          className="quick-add"
          onClick={() => setForm({})}
          aria-label={t("newOperation")}
        >
          <Plus size={25} />
        </button>
        <NavLink to="/accounts">
          <Wallet size={21} />
          {t("accounts")}
        </NavLink>
        <NavLink to="/more">
          <Ellipsis size={21} />
          {t("more")}
        </NavLink>
      </nav>
      {detail && (
        <OperationDetails
          o={detail}
          session={session}
          onClose={() => setDetail(null)}
          onEdit={(o) => {
            setDetail(null);
            setForm({ operation: o });
          }}
        />
      )}
      {form && (
        <OperationForm
          session={session}
          {...form}
          onClose={() => setForm(null)}
        />
      )}
    </div>
  );
}
function Offline({ onLogout }: { onLogout: () => void }) {
  const t = useT(),
    locale = useContext(LocaleContext);
  const [summary, setSummary] = useState<Summary>();
  useEffect(() => {
    const owner = localStorage.getItem("norocel-summary-owner");
    if (owner) void loadSummary(owner).then(setSummary);
  }, []);
  return (
    <main className="offline-screen">
      <h1>Norocel · {t("offline")}</h1>
      <p className="warning">{t("offlineHint")}</p>
      {summary ? (
        <>
          <p>
            {t("copiedAt")}: {summary.saved_at}
          </p>
          <div className="balance-grid">
            {currencies.map((c) => (
              <section className="balance-card" key={c}>
                <span>
                  {t("total")} · {c}
                </span>
                <h2>
                  {formatMoney(
                    summary.balances[c]?.total_minor ?? "0",
                    c,
                    locale,
                  )}
                </h2>
                <p>
                  {t("flow")} · {summary.month}:{" "}
                  {formatMoney(
                    summary.cash_flow[c]?.net_minor ?? "0",
                    c,
                    locale,
                  )}
                </p>
              </section>
            ))}
          </div>
        </>
      ) : (
        <p>{t("empty")}</p>
      )}
      <button onClick={onLogout}>{t("logout")}</button>
    </main>
  );
}
