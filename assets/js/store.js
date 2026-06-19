import { DEFAULT_CURRENCY, DEFAULT_LANGUAGE, DEFAULT_THEME } from "./config.js";

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
  language: DEFAULT_LANGUAGE,
  currency: DEFAULT_CURRENCY,
  theme: loadStoredTheme(),
  transactions: [],
  budgets: {},
  goals: []
};

export function getMonthTransactions(month, year) {
  return state.transactions.filter((transaction) => {
    const date = new Date(transaction.date);
    return date.getMonth() === month && date.getFullYear() === year;
  });
}

export function sumTransactions(transactions, type) {
  return transactions
    .filter((transaction) => transaction.type === type)
    .reduce((total, transaction) => total + transaction.amount, 0);
}
