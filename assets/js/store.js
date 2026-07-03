import { DEFAULT_CURRENCY, DEFAULT_LANGUAGE, DEFAULT_THEME, SUPPORTED_LANGUAGES } from "./config.js";

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
  language: loadStoredLanguage(),
  currency: DEFAULT_CURRENCY,
  theme: loadStoredTheme(),
  accounts: [],
  transactions: [],
  budgets: {},
  goals: [],
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

export function getGoalProgress(goal) {
  const saved = getGoalSavedAmount(goal);
  if (!goal.target) {
    return 0;
  }

  return Math.min(100, Math.round((saved / goal.target) * 100));
}
