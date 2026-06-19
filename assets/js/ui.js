import {
  CAT_COLORS,
  CAT_ICONS,
  DEFAULT_LANGUAGE,
  EXPENSE_CATEGORIES,
  INCOME_CATEGORIES,
  LOCALE_BY_LANGUAGE,
  SUPPORTED_CURRENCIES,
  SUPPORTED_LANGUAGES,
  SUPPORTED_THEMES,
  TRANSLATIONS
} from "./config.js";
import { getMonthTransactions, state, sumTransactions } from "./store.js";

const charts = {};
const LANGUAGE_FLAGS = {
  ro: "🇲🇩",
  ru: "🇷🇺",
  en: "🇺🇸"
};
const CURRENCY_SIGNS = {
  MDL: "L",
  EUR: "€",
  USD: "$"
};
const THEME_ICONS = {
  light: "☀",
  dark: "☾",
  system: "◐"
};

function getThemeColor(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function resolveColor(color) {
  if (!color?.startsWith("var(")) {
    return color;
  }

  const match = color.match(/var\((--[^)]+)\)/);
  return match ? getThemeColor(match[1]) : color;
}

export function t(key) {
  return TRANSLATIONS[state.language]?.[key] || TRANSLATIONS[DEFAULT_LANGUAGE]?.[key] || key;
}

export function getLocale() {
  return LOCALE_BY_LANGUAGE[state.language] || LOCALE_BY_LANGUAGE[DEFAULT_LANGUAGE];
}

export function formatCurrency(value) {
  return new Intl.NumberFormat(getLocale(), {
    style: "currency",
    currency: state.currency,
    maximumFractionDigits: 0
  }).format(value || 0);
}

export function formatDate(value) {
  return new Date(value).toLocaleDateString(getLocale(), {
    day: "numeric",
    month: "short",
    year: "numeric"
  });
}

export function getCategoryLabel(categoryKey) {
  return t(`category_${categoryKey}`);
}

export function showToast(message, type = "success") {
  const toast = document.createElement("div");
  const icon = type === "success" ? "✅" : type === "error" ? "❌" : "ℹ️";

  toast.className = `toast ${type}`;
  toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;
  document.getElementById("toast-container").appendChild(toast);
  window.setTimeout(() => toast.remove(), 3000);
}

export function renderStaticTexts() {
  document.documentElement.lang = state.language;
  document.title = t("meta_title");

  document.querySelectorAll("[data-i18n]").forEach((element) => {
    element.textContent = t(element.dataset.i18n);
  });

  document.querySelectorAll("[data-i18n-placeholder]").forEach((element) => {
    element.placeholder = t(element.dataset.i18nPlaceholder);
  });

  document.querySelectorAll("[data-i18n-title]").forEach((element) => {
    element.title = t(element.dataset.i18nTitle);
  });

  renderAuthMode();
  renderPreferenceSelectors();
}

export function renderPreferenceSelectors() {
  const languagePopover = document.getElementById("language-popover");
  const currencyPopover = document.getElementById("currency-popover");
  const themePopover = document.getElementById("theme-popover");
  const languageButton = document.getElementById("language-button");
  const currencyButton = document.getElementById("currency-button");
  const themeButton = document.getElementById("theme-button");

  languagePopover.innerHTML = SUPPORTED_LANGUAGES
    .map((language) => `<button class="round-option${language === state.language ? " active" : ""}" type="button" data-language-option="${language}" title="${t(`language_${language}`)}">${LANGUAGE_FLAGS[language] || language.toUpperCase()}</button>`)
    .join("");
  currencyPopover.innerHTML = SUPPORTED_CURRENCIES
    .map((currency) => `<button class="round-option${currency === state.currency ? " active" : ""}" type="button" data-currency-option="${currency}" title="${t(`currency_${currency}`)}">${CURRENCY_SIGNS[currency] || currency}</button>`)
    .join("");
  themePopover.innerHTML = SUPPORTED_THEMES
    .map((theme) => `<button class="round-option${theme === state.theme ? " active" : ""}" type="button" data-theme-option="${theme}" title="${t(`theme_${theme}`)}">${THEME_ICONS[theme] || "◐"}</button>`)
    .join("");

  languageButton.textContent = LANGUAGE_FLAGS[state.language] || state.language.toUpperCase();
  currencyButton.textContent = CURRENCY_SIGNS[state.currency] || state.currency;
  themeButton.textContent = THEME_ICONS[state.theme] || "◐";
  languagePopover.classList.toggle("open", state.openMenu === "language-popover");
  currencyPopover.classList.toggle("open", state.openMenu === "currency-popover");
  themePopover.classList.toggle("open", state.openMenu === "theme-popover");
}

export function updateMonthLabel() {
  const date = new Date(state.currentYear, state.currentMonth, 1);
  const label = new Intl.DateTimeFormat(getLocale(), { month: "long", year: "numeric" }).format(date);
  document.getElementById("month-label").textContent = label;
  document.getElementById("dash-period").textContent = label;
}

export function renderAuthMode() {
  document.querySelectorAll("[data-auth-mode]").forEach((button) => {
    button.classList.toggle("active", button.dataset.authMode === state.authMode);
  });

  document.getElementById("auth-name-group").classList.toggle("hidden", state.authMode !== "signup");
  document.getElementById("auth-password-group").classList.toggle("hidden", state.authMode === "reset");
  document.getElementById("auth-password").required = state.authMode !== "reset";
  document.getElementById("auth-submit").textContent = state.authMode === "signin"
    ? t("submit_signin")
    : state.authMode === "signup"
      ? t("submit_signup")
      : t("submit_reset");
  document.getElementById("auth-helper").textContent = state.authMode === "signin"
    ? t("auth_helper_signin")
    : state.authMode === "signup"
      ? t("auth_helper_signup")
      : t("auth_helper_reset");
}

export function renderUserHeader() {
  const email = state.profile?.email || state.user?.email || "user@example.com";
  const name = state.profile?.full_name || email.split("@")[0] || t("user_fallback");
  const avatarUrl = state.profile?.avatar_url || state.user?.user_metadata?.avatar_url || "";

  document.getElementById("user-name").textContent = name;
  document.getElementById("user-email").textContent = email;
  renderAvatar(document.getElementById("user-avatar"), name, avatarUrl, true);
  renderAvatar(document.getElementById("settings-avatar-preview"), name, avatarUrl);
}

export function renderSettingsPage() {
  const email = state.profile?.email || state.user?.email || "";
  const name = state.profile?.full_name || email.split("@")[0] || "";
  const avatarUrl = state.profile?.avatar_url || state.user?.user_metadata?.avatar_url || "";

  document.getElementById("settings-name").value = name;
  document.getElementById("settings-email").value = email;
  document.getElementById("settings-avatar").value = avatarUrl;
  renderAvatar(document.getElementById("settings-avatar-preview"), name || t("user_fallback"), avatarUrl);
}

export function renderCurrentPage() {
  renderNavigation();
  document.querySelector(".hero").classList.toggle("hidden", state.currentPage === "settings");
  document.querySelectorAll(".page").forEach((page) => {
    page.classList.toggle("active", page.id === `page-${state.currentPage}`);
  });

  if (state.currentPage === "dashboard") {
    renderDashboard();
  } else if (state.currentPage === "transactions") {
    renderTransactions();
  } else if (state.currentPage === "budget") {
    renderBudget();
  } else if (state.currentPage === "goals") {
    renderGoals();
  } else if (state.currentPage === "reports") {
    renderReports();
  } else if (state.currentPage === "settings") {
    renderSettingsPage();
  }
}

export function renderNavigation() {
  document.querySelectorAll(".nav-item").forEach((element) => {
    element.classList.toggle("active", element.dataset.page === state.currentPage);
  });
}

export function renderDashboard() {
  updateMonthLabel();

  const transactions = getMonthTransactions(state.currentMonth, state.currentYear);
  const income = sumTransactions(transactions, "income");
  const expense = sumTransactions(transactions, "expense");
  const balance = income - expense;
  const average = transactions.length ? Math.round(transactions.reduce((sum, transaction) => sum + transaction.amount, 0) / transactions.length) : 0;
  const savingsRate = income > 0 ? Math.round((balance / income) * 100) : 0;

  document.getElementById("dash-income").textContent = formatCurrency(income);
  document.getElementById("dash-expense").textContent = formatCurrency(expense);
  document.getElementById("dash-balance").textContent = formatCurrency(balance);
  document.getElementById("dash-balance").className = `stat-value ${balance >= 0 ? "blue" : "red"}`;
  document.getElementById("dash-income-count").textContent = `${transactions.filter((item) => item.type === "income").length} ${t("count_transactions")}`;
  document.getElementById("dash-expense-count").textContent = `${transactions.filter((item) => item.type === "expense").length} ${t("count_transactions")}`;
  document.getElementById("dash-total-tx").textContent = transactions.length;
  document.getElementById("dash-savings-rate").textContent = `${t("savings_rate")}: ${savingsRate}%`;
  document.getElementById("dash-avg").textContent = `${t("average")}: ${formatCurrency(average)}`;
  document.getElementById("dash-chart-period").textContent = new Intl.DateTimeFormat(getLocale(), { month: "long" }).format(new Date(state.currentYear, state.currentMonth, 1));

  renderDashChart(transactions);
  renderCategoryChart(transactions);
  renderRecentTransactions(transactions);
}

export function renderTransactions() {
  const filtered = state.transactions
    .filter((transaction) => state.filter === "all" || transaction.type === state.filter)
    .sort((left, right) => new Date(right.date) - new Date(left.date));

  document.getElementById("tx-count-label").textContent = `${filtered.length} ${t("count_transactions")}`;
  const container = document.getElementById("transactions-list");

  if (!filtered.length) {
    container.innerHTML = getEmptyState("💸", t("no_transactions_title"), t("no_transactions_body"));
    return;
  }

  container.innerHTML = filtered.map((transaction) => getTransactionMarkup(transaction, true)).join("");
}

export function renderBudget() {
  const transactions = getMonthTransactions(state.currentMonth, state.currentYear);
  const spentByCategory = {};

  transactions
    .filter((transaction) => transaction.type === "expense")
    .forEach((transaction) => {
      spentByCategory[transaction.category] = (spentByCategory[transaction.category] || 0) + transaction.amount;
    });

  const categories = Object.keys(state.budgets);
  const totalPlanned = Object.values(state.budgets).reduce((sum, value) => sum + value, 0);
  const totalSpent = categories.reduce((sum, category) => sum + (spentByCategory[category] || 0), 0);

  document.getElementById("budget-planned").textContent = formatCurrency(totalPlanned);
  document.getElementById("budget-spent").textContent = formatCurrency(totalSpent);

  const container = document.getElementById("budget-list");
  if (!categories.length) {
    container.innerHTML = getEmptyState("🎯", t("no_budgets_title"), t("no_budgets_body"));
    return;
  }

  container.innerHTML = categories.map((category) => {
    const limit = state.budgets[category];
    const spent = spentByCategory[category] || 0;
    const percent = Math.min(100, Math.round((spent / limit) * 100));
    const color = percent >= 100 ? getThemeColor("--expense") : percent >= 80 ? getThemeColor("--accent-4") : getThemeColor("--income");

    return `
      <div class="budget-item">
        <div class="budget-item-header">
          <div class="budget-item-name">${CAT_ICONS[category] || "📦"} ${getCategoryLabel(category)}</div>
          <div style="display:flex;gap:8px;align-items:center">
            <div class="budget-item-amounts"><span>${formatCurrency(spent)}</span> / ${formatCurrency(limit)}</div>
            <button class="tx-btn del" type="button" data-action="delete-budget" data-category="${category}" title="${t("action_delete")}">🗑️</button>
          </div>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:${percent}%;background:${color}"></div>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
          ${percent}% ${t("budget_used")}${percent >= 100 ? ` — <span style="color:var(--expense)">${t("budget_exceeded")}</span>` : ""}
        </div>
      </div>
    `;
  }).join("");
}

export function renderGoals() {
  const container = document.getElementById("goals-grid");

  if (!state.goals.length) {
    container.innerHTML = `<div style="grid-column:1/-1">${getEmptyState("🏆", t("no_goals_title"), t("no_goals_body"))}</div>`;
    return;
  }

  container.innerHTML = state.goals.map((goal) => {
    const percent = Math.min(100, Math.round((goal.saved / goal.target) * 100));
    const remaining = Math.max(0, goal.target - goal.saved);
    const color = percent >= 100 ? getThemeColor("--income") : percent >= 60 ? getThemeColor("--accent-4") : getThemeColor("--accent-2");
    const deadline = getDeadlineMarkup(goal.deadline);

    return `
      <article class="goal-card">
        <div class="goal-header">
          <div>
            <div style="font-size:32px">${goal.icon || "🎯"}</div>
            <div class="goal-name">${goal.name}</div>
            <div class="goal-amounts"><strong>${formatCurrency(goal.saved)}</strong> / ${formatCurrency(goal.target)}</div>
          </div>
          <div>
            <div class="goal-percent">${percent}%</div>
            <div class="goal-actions">
              <button class="tx-btn" type="button" data-action="edit-goal" data-id="${goal.id}">✏️</button>
              <button class="tx-btn del" type="button" data-action="delete-goal" data-id="${goal.id}">🗑️</button>
            </div>
          </div>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:${percent}%;background:${color}"></div>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:6px">${t("goal_remaining")}: <strong style="color:var(--text)">${formatCurrency(remaining)}</strong></div>
        ${deadline}
      </article>
    `;
  }).join("");
}

export function renderReports() {
  renderMonthlyChart();
  renderExpensePieChart();
  renderBalanceChart();
  renderTopCategories();
}

export function setTransactionType(type) {
  document.getElementById("tx-type").value = type;
  document.getElementById("btn-income").className = `type-btn${type === "income" ? " active-income" : ""}`;
  document.getElementById("btn-expense").className = `type-btn${type === "expense" ? " active-expense" : ""}`;

  const categories = type === "income" ? INCOME_CATEGORIES : EXPENSE_CATEGORIES;
  document.getElementById("tx-category").innerHTML = categories
    .map((category) => `<option value="${category}">${CAT_ICONS[category] || ""} ${getCategoryLabel(category)}</option>`)
    .join("");
}

function renderRecentTransactions(transactions) {
  const recent = [...transactions]
    .sort((left, right) => new Date(right.date) - new Date(left.date))
    .slice(0, 8);

  const container = document.getElementById("recent-transactions");
  if (!recent.length) {
    container.innerHTML = getEmptyState("💸", t("no_transactions_title"), t("no_recent_transactions_body"));
    return;
  }

  container.innerHTML = recent.map((transaction) => getTransactionMarkup(transaction)).join("");
}

function renderAvatar(element, name, avatarUrl, keepGear = false) {
  if (!element) {
    return;
  }

  const initial = (name || t("user_fallback")).slice(0, 1).toUpperCase();
  element.style.backgroundImage = avatarUrl ? `linear-gradient(var(--avatar-image-tint), var(--avatar-image-tint)), url("${avatarUrl}")` : "";
  element.style.backgroundSize = avatarUrl ? "cover" : "";
  element.style.backgroundPosition = avatarUrl ? "center" : "";
  element.textContent = avatarUrl ? "" : initial;

  if (keepGear) {
    const gear = document.createElement("span");
    gear.className = "avatar-gear";
    gear.setAttribute("aria-hidden", "true");
    gear.textContent = "⚙";
    element.appendChild(gear);
  }
}

function renderDashChart(transactions) {
  const daysInMonth = new Date(state.currentYear, state.currentMonth + 1, 0).getDate();
  const labels = Array.from({ length: daysInMonth }, (_, index) => index + 1);
  const incomes = new Array(daysInMonth).fill(0);
  const expenses = new Array(daysInMonth).fill(0);

  transactions.forEach((transaction) => {
    const dayIndex = new Date(transaction.date).getDate() - 1;
    if (transaction.type === "income") {
      incomes[dayIndex] += transaction.amount;
    } else {
      expenses[dayIndex] += transaction.amount;
    }
  });

  destroyChart("dash");
  charts.dash = new window.Chart(document.getElementById("dashChart"), {
    type: "bar",
    data: {
      labels,
      datasets: [
        { label: t("btn_income"), data: incomes, backgroundColor: getThemeColor("--chart-income"), borderRadius: 4 },
        { label: t("btn_expense"), data: expenses, backgroundColor: getThemeColor("--chart-expense"), borderRadius: 4 }
      ]
    },
    options: getChartOptions()
  });
}

function renderCategoryChart(transactions) {
  const expenses = transactions.filter((transaction) => transaction.type === "expense");
  const grouped = {};

  expenses.forEach((transaction) => {
    grouped[transaction.category] = (grouped[transaction.category] || 0) + transaction.amount;
  });

  const categories = Object.keys(grouped);
  destroyChart("cat");

  const canvas = document.getElementById("catChart");
  if (!categories.length) {
    canvas.getContext("2d").clearRect(0, 0, canvas.width, canvas.height);
    return;
  }

  charts.cat = new window.Chart(canvas, {
    type: "doughnut",
    data: {
      labels: categories.map(getCategoryLabel),
      datasets: [{
        data: categories.map((category) => grouped[category]),
        backgroundColor: categories.map((category) => resolveColor(CAT_COLORS[category]) || getThemeColor("--accent-2")),
        borderColor: getThemeColor("--chart-surface-border"),
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "64%",
      plugins: {
        legend: {
          position: "bottom",
          labels: { color: getThemeColor("--chart-label"), font: { size: 11 }, boxWidth: 12, padding: 10 }
        }
      }
    }
  });
}

function renderMonthlyChart() {
  const labels = [];
  const incomes = [];
  const expenses = [];

  for (let offset = 5; offset >= 0; offset -= 1) {
    let month = state.currentMonth - offset;
    let year = state.currentYear;

    while (month < 0) {
      month += 12;
      year -= 1;
    }

    const date = new Date(year, month, 1);
    const monthTransactions = getMonthTransactions(month, year);
    labels.push(new Intl.DateTimeFormat(getLocale(), { month: "short", year: "2-digit" }).format(date));
    incomes.push(sumTransactions(monthTransactions, "income"));
    expenses.push(sumTransactions(monthTransactions, "expense"));
  }

  destroyChart("monthly");
  charts.monthly = new window.Chart(document.getElementById("monthlyChart"), {
    type: "bar",
    data: {
      labels,
      datasets: [
        { label: t("btn_income"), data: incomes, backgroundColor: getThemeColor("--chart-income"), borderRadius: 6 },
        { label: t("btn_expense"), data: expenses, backgroundColor: getThemeColor("--chart-expense"), borderRadius: 6 }
      ]
    },
    options: getChartOptions()
  });
}

function renderExpensePieChart() {
  const expenses = state.transactions.filter((transaction) => transaction.type === "expense");
  const grouped = {};
  expenses.forEach((transaction) => {
    grouped[transaction.category] = (grouped[transaction.category] || 0) + transaction.amount;
  });

  const categories = Object.keys(grouped).sort((left, right) => grouped[right] - grouped[left]);
  destroyChart("expensePie");

  if (!categories.length) {
    return;
  }

  charts.expensePie = new window.Chart(document.getElementById("expensePieChart"), {
    type: "pie",
    data: {
      labels: categories.map(getCategoryLabel),
      datasets: [{
        data: categories.map((category) => grouped[category]),
        backgroundColor: categories.map((category) => resolveColor(CAT_COLORS[category]) || getThemeColor("--accent-2")),
        borderColor: getThemeColor("--chart-surface-border"),
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "bottom",
          labels: { color: getThemeColor("--chart-label"), font: { size: 11 }, boxWidth: 12 }
        }
      }
    }
  });
}

function renderBalanceChart() {
  const sorted = [...state.transactions].sort((left, right) => new Date(left.date) - new Date(right.date));
  const labels = [];
  const balances = [];
  let balance = 0;

  sorted.forEach((transaction) => {
    balance += transaction.type === "income" ? transaction.amount : -transaction.amount;
    labels.push(formatDate(transaction.date));
    balances.push(balance);
  });

  destroyChart("balance");
  if (!labels.length) {
    return;
  }

  charts.balance = new window.Chart(document.getElementById("balanceChart"), {
    type: "line",
    data: {
      labels,
      datasets: [{
        label: t("stat_balance"),
        data: balances,
        borderColor: getThemeColor("--chart-line"),
        backgroundColor: getThemeColor("--chart-line-fill"),
        fill: true,
        tension: 0.35,
        pointRadius: 3,
        pointBackgroundColor: getThemeColor("--chart-line")
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: getChartScales()
    }
  });
}

function renderTopCategories() {
  const grouped = {};
  state.transactions
    .filter((transaction) => transaction.type === "expense")
    .forEach((transaction) => {
      grouped[transaction.category] = (grouped[transaction.category] || 0) + transaction.amount;
    });

  const total = Object.values(grouped).reduce((sum, value) => sum + value, 0);
  const sorted = Object.entries(grouped).sort((left, right) => right[1] - left[1]).slice(0, 6);
  const container = document.getElementById("top-categories");

  if (!sorted.length) {
    container.innerHTML = getEmptyState("📊", t("no_data_title"), t("no_data_body"));
    return;
  }

  container.innerHTML = sorted.map(([category, amount]) => {
    const percent = Math.round((amount / total) * 100);
    return `
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px">
          <span>${CAT_ICONS[category] || "📦"} ${getCategoryLabel(category)}</span>
          <span style="font-weight:600">${formatCurrency(amount)} <span style="color:var(--text-muted);font-weight:400;font-size:12px">(${percent}%)</span></span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:${percent}%;background:${resolveColor(CAT_COLORS[category]) || getThemeColor("--accent-2")}"></div>
        </div>
      </div>
    `;
  }).join("");
}

function getTransactionMarkup(transaction, withActions = false) {
  const color = resolveColor(CAT_COLORS[transaction.category]) || getThemeColor("--accent-2");
  const icon = CAT_ICONS[transaction.category] || "💳";
  const sign = transaction.type === "income" ? "+" : "-";

  return `
    <div class="transaction-item">
      <div class="tx-icon" style="background:${color}22;color:${color}">${icon}</div>
      <div class="tx-info">
        <div class="tx-name">${transaction.desc || getCategoryLabel(transaction.category)}</div>
        <div class="tx-date">${formatDate(transaction.date)}</div>
        <span class="tx-category">${getCategoryLabel(transaction.category)}</span>
      </div>
      <div class="tx-amount ${transaction.type}">${sign}${formatCurrency(transaction.amount)}</div>
      ${withActions ? `
        <div class="tx-actions">
          <button class="tx-btn" type="button" data-action="edit-transaction" data-id="${transaction.id}">✏️</button>
          <button class="tx-btn del" type="button" data-action="delete-transaction" data-id="${transaction.id}">🗑️</button>
        </div>
      ` : ""}
    </div>
  `;
}

function getDeadlineMarkup(deadline) {
  if (!deadline) {
    return "";
  }

  const days = Math.ceil((new Date(deadline) - new Date()) / 86400000);
  return `<div style="font-size:12px;color:var(--text-muted);margin-top:8px">📅 ${days > 0 ? `${days} ${t("days_left")}` : t("deadline_expired")}</div>`;
}

function getEmptyState(icon, title, text) {
  return `
    <div class="empty-state">
      <div class="empty-icon">${icon}</div>
      <h3>${title}</h3>
      <p>${text}</p>
    </div>
  `;
}

function destroyChart(name) {
  if (charts[name]) {
    charts[name].destroy();
    charts[name] = null;
  }
}

function getChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        labels: { color: getThemeColor("--chart-label"), font: { size: 12 } }
      }
    },
    scales: getChartScales()
  };
}

function getChartScales() {
  return {
    x: {
      grid: { color: getThemeColor("--chart-grid") },
      ticks: { color: getThemeColor("--chart-label"), font: { size: 11 }, maxTicksLimit: 10 }
    },
    y: {
      grid: { color: getThemeColor("--chart-grid") },
      ticks: { color: getThemeColor("--chart-label"), font: { size: 11 }, callback: (value) => formatCurrency(value) }
    }
  };
}
