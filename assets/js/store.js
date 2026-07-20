import { DEFAULT_CURRENCY, DEFAULT_LANGUAGE, DEFAULT_THEME, SUPPORTED_LANGUAGES } from "./config.js?v=20260720-2";
import { EXPENSE_CATEGORIES, INCOME_CATEGORIES, normalizeCategory } from "./i18n.js?v=20260720-2";

function loadStoredLanguage() {
  if (typeof window === "undefined") {
    return DEFAULT_LANGUAGE;
  }

  try {
    const language = window.localStorage.getItem("norocel-language");
    return SUPPORTED_LANGUAGES.includes(language) ? language : DEFAULT_LANGUAGE;
  } catch {
    return DEFAULT_LANGUAGE;
  }
}

function loadStoredTheme() {
  if (typeof window === "undefined") {
    return DEFAULT_THEME;
  }

  try {
    const theme = window.localStorage.getItem("norocel-theme");
    return theme || DEFAULT_THEME;
  } catch {
    return DEFAULT_THEME;
  }
}

export const state = {
  user: null,
  profile: null,
  currentPage: "dashboard",
  openMenu: null,
  currentMonth: new Date().getMonth(),
  currentYear: new Date().getFullYear(),
  filter: "all",
  authMode: "signin",
  isAdminMode: false,
  adminUsers: [],
  adminStats: null,
  language: loadStoredLanguage(),
  currency: DEFAULT_CURRENCY,
  theme: loadStoredTheme(),
  accounts: [],
  categories: [],
  transactions: [],
  budgets: {},
  goals: [],
  liabilities: [],
  activeAccountId: ""
};

export function getMonthTransactions(month, year) {
  return state.transactions.filter((transaction) => {
    const date = new Date(transaction.date);
    return (
      date.getMonth() === month
      && date.getFullYear() === year
      && (transaction.type === "income" || transaction.type === "expense")
      && transaction.currencyCode === state.currency
    );
  });
}

export function sumTransactions(transactions, type) {
  return transactions
    .filter((transaction) => transaction.type === type)
    .reduce((total, transaction) => total + transaction.amount, 0);
}

export function getAccountById(accountId) {
  return state.accounts.find((account) => account.id === accountId) || null;
}

export function getAccountBalance(accountId) {
  const account = getAccountById(accountId);
  if (!account) {
    return 0;
  }

  return state.transactions.reduce((balance, transaction) => {
    if (transaction.type === "income" && transaction.accountId === accountId) {
      return balance + transaction.amount;
    }

    if (transaction.type === "expense" && transaction.accountId === accountId) {
      return balance - transaction.amount;
    }

    if ((transaction.type === "transfer" || transaction.type === "exchange") && transaction.fromAccountId === accountId) {
      return balance - transaction.amount;
    }

    if ((transaction.type === "transfer" || transaction.type === "exchange") && transaction.toAccountId === accountId) {
      return balance + (transaction.convertedAmount || 0);
    }

    return balance;
  }, account.openingBalance || 0);
}

export function getSavingsAccounts() {
  return state.accounts.filter((account) => account.type === "savings");
}

export function getCategories(type) {
  const defaultKeys = type === "income" ? INCOME_CATEGORIES : EXPENSE_CATEGORIES;
  const defaultCategories = defaultKeys.map((key) => ({
    key,
    type,
    isDefault: true
  }));
  const customCategories = state.categories
    .filter((category) => category.type === type)
    .map((category) => ({
      ...category,
      key: normalizeCategory(category.key),
      isDefault: false
    }));

  return defaultCategories.concat(customCategories);
}

export function getCategoryByKey(categoryKey, type = "") {
  const normalizedKey = normalizeCategory(categoryKey);
  const customCategory = state.categories.find((category) => (
    normalizeCategory(category.key) === normalizedKey
    && (!type || category.type === type)
  ));

  if (customCategory) {
    return {
      ...customCategory,
      key: normalizedKey,
      isDefault: false
    };
  }

  const defaultType = INCOME_CATEGORIES.includes(normalizedKey) ? "income" : EXPENSE_CATEGORIES.includes(normalizedKey) ? "expense" : "";
  if (defaultType && (!type || type === defaultType)) {
    return {
      key: normalizedKey,
      type: defaultType,
      isDefault: true
    };
  }

  return null;
}

export function getGoalSavedAmount(goal) {
  if (goal.savingsAccountId) {
    return getAccountBalance(goal.savingsAccountId);
  }

  return goal.saved || 0;
}

export function getGoalSpentAmount(goalId) {
  return state.transactions
    .filter((transaction) => transaction.type === "expense" && transaction.goalId === goalId)
    .reduce((total, transaction) => total + transaction.amount, 0);
}

export function getGoalFundedAmount(goal) {
  return getGoalSavedAmount(goal) + getGoalSpentAmount(goal.id);
}

export function getGoalProgress(goal) {
  if (!goal.target) {
    return 0;
  }

  return Math.min(100, Math.round((getGoalFundedAmount(goal) / goal.target) * 100));
}

export function getGoalComputedStatus(goal) {
  if (goal.status === "cancelled" || goal.status === "spent") {
    return goal.status;
  }

  if (getGoalSpentAmount(goal.id) >= goal.target) {
    return "spent";
  }

  return getGoalFundedAmount(goal) >= goal.target ? "reached" : "active";
}

export function getOpenLiabilities() {
  return state.liabilities.filter((liability) => liability.status !== "settled");
}

export function getLiabilityTotalsByCurrency(type) {
  return getOpenLiabilities()
    .filter((liability) => (type ? liability.type === type : true))
    .reduce((totals, liability) => {
      totals[liability.currencyCode] = (totals[liability.currencyCode] || 0) + liability.amount;
      return totals;
    }, {});
}
