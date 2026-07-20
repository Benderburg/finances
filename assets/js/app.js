import {
  ACCOUNT_TYPES,
  DEFAULT_CURRENCY,
  DEFAULT_LANGUAGE,
  LIABILITY_TYPES,
  SUPPORTED_CURRENCIES
} from "./config.js?v=20260720-2";
import {
  state,
  getAccountBalance,
  getAccountById,
  getCategories,
  getCategoryByKey,
  getGoalComputedStatus,
  getGoalFundedAmount,
  getGoalProgress,
  getGoalSavedAmount,
  getGoalSpentAmount,
  getSavingsAccounts
} from "./store.js?v=20260720-2";
import { createSupabaseClient, isSupabaseConfigured } from "./supabase.js?v=20260720-2";
import {
  createTransaction,
  deleteAccountById,
  deleteBudgetByCategory,
  deleteCategoryById,
  deleteGoalById,
  deleteLiabilityById,
  deleteTransactionById,
  exportUserData,
  importUserData,
  loadAdminWorkspace,
  loadUserWorkspace,
  saveAccount,
  saveBudget,
  saveCategory,
  saveGoal,
  saveLiability,
  saveProfile,
  saveTransaction,
  sendPasswordReset,
  settleLiability,
  signIn,
  signOut,
  signUp,
  updateProfileDetails,
  updateAdminUser,
  updateProfilePreferences,
  updateUserProfile
} from "./supabase-api.js?v=20260720-2";
import {
  formatMoney,
  getCategoryLabel,
  renderBudget,
  renderCurrentPage,
  renderPreferenceSelectors,
  renderStaticTexts,
  renderUserHeader,
  setTransactionType,
  showToast,
  t,
  updateMonthLabel
} from "./ui.js?v=20260720-2";

let supabase;
let confirmResolver = null;

window.addEventListener("DOMContentLoaded", init);

async function init() {
  state.isAdminMode = isAdminRoute();
  if (state.isAdminMode) {
    state.currentPage = "admin-dashboard";
    if (new URLSearchParams(window.location.search).has("admin")) {
      window.history.replaceState(null, "", "./admin");
    }
  }
  applyTheme(state.theme);
  bindThemeMedia();
  renderStaticTexts();

  if (!isSupabaseConfigured()) {
    disableAuth(t("error_setup_required"));
    bindStaticEvents();
    finishBoot();
    return;
  }

  supabase = createSupabaseClient();
  bindStaticEvents();

  const { data, error } = await supabase.auth.getSession();
  if (error) {
    showToast(error.message, "error");
  }

  await applySession(data.session);
  finishBoot();

  supabase.auth.onAuthStateChange(async (_event, session) => {
    await applySession(session);
  });
}

function bindStaticEvents() {
  document.addEventListener("click", handleDocumentClick);
  document.getElementById("auth-form").addEventListener("submit", handleAuthSubmit);
  document.getElementById("transaction-form").addEventListener("submit", handleTransactionSubmit);
  document.getElementById("category-form").addEventListener("submit", handleCategorySubmit);
  document.getElementById("admin-user-form").addEventListener("submit", handleAdminUserSubmit);
  document.getElementById("budget-form").addEventListener("submit", handleBudgetSubmit);
  document.getElementById("goal-form").addEventListener("submit", handleGoalSubmit);
  document.getElementById("liability-form").addEventListener("submit", handleLiabilitySubmit);
  document.getElementById("liability-settle-form").addEventListener("submit", handleLiabilitySettleSubmit);
  document.getElementById("account-form").addEventListener("submit", handleAccountSubmit);
  document.getElementById("transfer-form").addEventListener("submit", handleTransferSubmit);
  document.getElementById("account-expense-form").addEventListener("submit", handleAccountExpenseSubmit);
  document.getElementById("goal-spend-form").addEventListener("submit", handleGoalSpendSubmit);
  document.getElementById("confirm-form").addEventListener("submit", handleConfirmSubmit);
  document.getElementById("settings-form").addEventListener("submit", handleSettingsSubmit);
  document.getElementById("import-file").addEventListener("change", handleImport);
  document.getElementById("settings-name").addEventListener("input", handleAvatarPreview);
  document.getElementById("settings-avatar").addEventListener("input", handleAvatarPreview);
  document.getElementById("goal-currency").addEventListener("change", syncGoalAccountOptions);
  document.getElementById("category-name").addEventListener("input", updateCategoryMatches);
  document.getElementById("category-type").addEventListener("change", updateCategoryMatches);
}

async function applySession(session) {
  if (!session?.user) {
    state.user = null;
    state.profile = null;
    state.accounts = [];
    state.categories = [];
    state.transactions = [];
    state.budgets = {};
    state.goals = [];
    state.liabilities = [];
    state.currency = DEFAULT_CURRENCY;
    state.currentPage = "dashboard";
    state.activeAccountId = "";
    state.openMenu = null;
    state.adminUsers = [];
    state.adminStats = null;
    renderStaticTexts();
    showAuth();
    return;
  }

  state.user = session.user;

  try {
    await saveProfile(supabase, session.user, {
      language: state.language,
      currency: state.currency
    });
    await hydrateWorkspace();
    if (state.isAdminMode && !state.profile?.is_admin) {
      showToast(t("error_admin_required"), "error");
      await signOut(supabase);
      return;
    }
    showApp();
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function hydrateWorkspace() {
  const workspace = await loadUserWorkspace(supabase, state.user.id);
  state.profile = workspace.profile;
  state.language = workspace.profile?.language || DEFAULT_LANGUAGE;
  state.currency = workspace.profile?.currency || DEFAULT_CURRENCY;
  state.accounts = workspace.accounts;
  state.categories = workspace.categories;
  state.transactions = workspace.transactions;
  state.budgets = workspace.budgets;
  state.goals = workspace.goals;
  state.liabilities = workspace.liabilities;

  if (!state.activeAccountId || !state.accounts.some((account) => account.id === state.activeAccountId)) {
    state.activeAccountId = getSavingsAccounts()[0]?.id || state.accounts[0]?.id || "";
  }

  renderStaticTexts();
  renderUserHeader();
  updateMonthLabel();
  renderCurrentPage();

  if (state.isAdminMode) {
    await hydrateAdminWorkspace();
  }
}

async function hydrateAdminWorkspace() {
  const workspace = await loadAdminWorkspace(supabase);
  state.adminUsers = workspace.users;
  state.adminStats = workspace.stats;
  renderCurrentPage();
}

function handleDocumentClick(event) {
  const menuToggle = event.target.closest("[data-menu-toggle]");
  if (menuToggle) {
    toggleMenu(menuToggle.dataset.menuToggle);
    return;
  }

  const languageOption = event.target.closest("[data-language-option]");
  if (languageOption) {
    handleLanguageChange(languageOption.dataset.languageOption);
    return;
  }

  const currencyOption = event.target.closest("[data-currency-option]");
  if (currencyOption) {
    handleCurrencyChange(currencyOption.dataset.currencyOption);
    return;
  }

  const themeOption = event.target.closest("[data-theme-option]");
  if (themeOption) {
    handleThemeChange(themeOption.dataset.themeOption);
    return;
  }

  if (state.openMenu && !event.target.closest(".round-menu")) {
    state.openMenu = null;
    renderPreferenceSelectors();
  }

  const modeButton = event.target.closest("[data-auth-mode]");
  if (modeButton) {
    state.authMode = modeButton.dataset.authMode;
    renderStaticTexts();
    return;
  }

  const pageTrigger = event.target.closest("[data-page]");
  if (pageTrigger) {
    state.currentPage = pageTrigger.dataset.page;
    renderCurrentPage();
    closeSidebarOnMobile();
    return;
  }

  const filterButton = event.target.closest("[data-filter]");
  if (filterButton) {
    state.filter = filterButton.dataset.filter;
    document.querySelectorAll("[data-filter]").forEach((chip) => chip.classList.toggle("active", chip === filterButton));
    renderCurrentPage();
    return;
  }

  const typeButton = event.target.closest("[data-type]");
  if (typeButton) {
    setTransactionType(typeButton.dataset.type);
    return;
  }

  const closeButton = event.target.closest("[data-close-modal]");
  if (closeButton) {
    closeModal(closeButton.dataset.closeModal);
    return;
  }

  if (event.target.classList.contains("modal-overlay")) {
    closeModal(event.target.id);
    return;
  }

  const clickedButtonId = event.target.closest("button")?.id;

  if (clickedButtonId === "prev-month") {
    changeMonth(-1);
    return;
  }

  if (clickedButtonId === "next-month") {
    changeMonth(1);
    return;
  }

  if (clickedButtonId === "sidebar-toggle") {
    document.getElementById("sidebar").classList.toggle("open");
    return;
  }

  if (clickedButtonId === "quick-income") {
    openTransactionModal("income");
    return;
  }

  if (clickedButtonId === "quick-expense") {
    openTransactionModal("expense");
    return;
  }

  if (clickedButtonId === "add-budget-button") {
    openBudgetModal();
    return;
  }

  if (clickedButtonId === "settings-add-category") {
    openCategoryModal({ type: "expense" });
    return;
  }

  if (clickedButtonId === "category-delete-button") {
    const categoryId = document.getElementById("category-edit-id").value;
    if (categoryId) {
      closeModal("category-modal");
      deleteCategory(categoryId);
    }
    return;
  }

  if (clickedButtonId === "admin-user-delete-button") {
    return;
  }

  if (clickedButtonId === "add-goal-button") {
    openGoalModal();
    return;
  }

  if (clickedButtonId === "add-liability-button") {
    openLiabilityModal();
    return;
  }

  if (clickedButtonId === "add-account-button") {
    openAccountModal();
    return;
  }

  if (clickedButtonId === "account-delete-button") {
    const accountId = document.getElementById("account-edit-id").value;
    if (accountId) {
      closeModal("account-modal");
      deleteAccount(accountId);
    }
    return;
  }

  if (clickedButtonId === "open-transfer-button") {
    openTransferModal();
    return;
  }

  if (clickedButtonId === "print-reports-button") {
    window.print();
    return;
  }

  if (clickedButtonId === "logout-button") {
    handleLogout();
    return;
  }

  if (clickedButtonId === "export-button") {
    handleExport();
    return;
  }

  const actionButton = event.target.closest("[data-action]");
  if (actionButton) {
    runAction(actionButton.dataset.action, actionButton.dataset);
  }
}

function isAdminRoute() {
  const normalizedPath = window.location.pathname.replace(/\/+$/, "");
  return normalizedPath.endsWith("/admin") || new URLSearchParams(window.location.search).has("admin");
}

function changeMonth(direction) {
  state.currentMonth += direction;

  if (state.currentMonth > 11) {
    state.currentMonth = 0;
    state.currentYear += 1;
  }

  if (state.currentMonth < 0) {
    state.currentMonth = 11;
    state.currentYear -= 1;
  }

  updateMonthLabel();
  renderCurrentPage();
}

async function handleAuthSubmit(event) {
  event.preventDefault();

  if (!supabase) {
    return;
  }

  const email = document.getElementById("auth-email").value.trim();
  const password = document.getElementById("auth-password").value;
  const fullName = document.getElementById("auth-name").value.trim();

  try {
    if (state.authMode === "signin") {
      await signIn(supabase, email, password);
      showToast(t("toast_signin_success"));
      return;
    }

    if (state.authMode === "signup") {
      await signUp(supabase, email, password, fullName, state.language);
      showToast(t("toast_signup_success"));
      return;
    }

    await sendPasswordReset(supabase, email);
    showToast(t("toast_reset_sent"));
  } catch (error) {
    showToast(error.message, "error");
  }
}

function openTransactionModal(type = "income", accountId = "") {
  document.getElementById("tx-edit-id").value = "";
  document.getElementById("modal-title").textContent = t("modal_new_transaction");
  document.getElementById("transaction-form").reset();
  document.getElementById("tx-date").value = new Date().toISOString().split("T")[0];
  populateAccountSelect("tx-account", state.accounts, accountId || getDefaultRegularAccountId());
  setTransactionType(type);
  openModal("add-modal");
}

function editTransaction(transactionId) {
  const transaction = state.transactions.find((item) => item.id === transactionId);
  if (!transaction || (transaction.type !== "income" && transaction.type !== "expense")) {
    return;
  }

  document.getElementById("tx-edit-id").value = transaction.id;
  document.getElementById("modal-title").textContent = t("modal_edit_transaction");
  document.getElementById("tx-amount").value = transaction.amount;
  document.getElementById("tx-desc").value = transaction.desc || "";
  document.getElementById("tx-date").value = transaction.date;
  populateAccountSelect("tx-account", state.accounts, transaction.accountId);
  setTransactionType(transaction.type);
  document.getElementById("tx-category").value = transaction.category;
  openModal("add-modal");
}

async function handleTransactionSubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const amount = Number.parseFloat(document.getElementById("tx-amount").value);
    const description = document.getElementById("tx-desc").value.trim();
    const date = document.getElementById("tx-date").value;
    const category = document.getElementById("tx-category").value;
    const type = document.getElementById("tx-type").value;
    const accountId = document.getElementById("tx-account").value;
    const editId = document.getElementById("tx-edit-id").value;
    const account = getAccountById(accountId);

    if (!amount || amount <= 0) {
      showToast(t("error_invalid_amount"), "error");
      return;
    }

    if (!date) {
      showToast(t("error_choose_date"), "error");
      return;
    }

    if (!account) {
      showToast(t("error_select_account"), "error");
      return;
    }

    const payload = {
      id: editId || undefined,
      amount,
      desc: description,
      date,
      category,
      type,
      accountId,
      currencyCode: account.currencyCode
    };

    try {
      if (editId) {
        await saveTransaction(supabase, state.user.id, payload);
        showToast(t("toast_tx_updated"));
      } else {
        await createTransaction(supabase, state.user.id, payload);
        showToast(t("toast_tx_added"));
      }

      await hydrateWorkspace();
      closeModal("add-modal");
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

async function deleteTransaction(transactionId) {
  if (!await confirmAction(t("confirm_delete_transaction"), t("action_delete"))) {
    return;
  }

  try {
    await deleteTransactionById(supabase, state.user.id, transactionId);
    await hydrateWorkspace();
    showToast(t("toast_tx_deleted"), "info");
  } catch (error) {
    showToast(error.message, "error");
  }
}

function openCategoryModal({ categoryId = "", type = "expense", sourceSelectId = "" } = {}) {
  const form = document.getElementById("category-form");
  form.reset();
  document.getElementById("category-edit-id").value = categoryId;
  document.getElementById("category-source-select").value = sourceSelectId;
  document.getElementById("category-modal-title").textContent = categoryId ? t("modal_edit_category") : t("modal_new_category");
  document.getElementById("category-delete-button").classList.toggle("hidden", !categoryId);
  document.getElementById("category-type").disabled = Boolean(categoryId);

  if (categoryId) {
    const category = state.categories.find((item) => item.id === categoryId);
    if (!category) {
      return;
    }

    document.getElementById("category-name").value = category.name;
    document.getElementById("category-type").value = category.type;
  } else {
    document.getElementById("category-type").value = type;
  }

  updateCategoryMatches();
  openModal("category-modal");
}

function openQuickCategoryModal(dataset) {
  const sourceSelectId = dataset.selectId || "";
  const sourceType = dataset.typeSource ? document.getElementById(dataset.typeSource)?.value : "";
  const type = dataset.categoryType || sourceType || "expense";
  openCategoryModal({ type, sourceSelectId });
}

async function handleCategorySubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const editId = document.getElementById("category-edit-id").value;
    const sourceSelectId = document.getElementById("category-source-select").value;
    const existingCategory = state.categories.find((category) => category.id === editId);
    const name = document.getElementById("category-name").value.trim();
    const type = existingCategory?.type || document.getElementById("category-type").value;

    if (!name) {
      showToast(t("error_enter_category_name"), "error");
      return;
    }

    if (findExactCategoryMatch(name, type, editId)) {
      showToast(t("error_category_exists"), "error");
      return;
    }

    const key = existingCategory?.key || createCategoryKey(name, type);

    try {
      await saveCategory(supabase, state.user.id, {
        id: editId || undefined,
        key,
        name,
        type
      });
      await hydrateWorkspace();

      if (sourceSelectId) {
        refreshCategorySelect(sourceSelectId, type, key);
      }

      closeModal("category-modal");
      showToast(editId ? t("toast_category_updated") : t("toast_category_added"));
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

async function deleteCategory(categoryId) {
  if (!await confirmAction(t("confirm_delete_category"), t("action_delete"))) {
    return;
  }

  try {
    await deleteCategoryById(supabase, state.user.id, categoryId);
    await hydrateWorkspace();
    showToast(t("toast_category_deleted"), "info");
  } catch (error) {
    showToast(error.message, "error");
  }
}

function openAdminUserModal(userId) {
  const user = state.adminUsers.find((item) => item.id === userId);
  if (!user) {
    return;
  }

  document.getElementById("admin-user-form").reset();
  document.getElementById("admin-user-id").value = user.id;
  document.getElementById("admin-user-name").value = user.fullName || "";
  document.getElementById("admin-user-email").value = user.email || "";
  document.getElementById("admin-user-billing").value = user.billing || "regular";
  document.getElementById("admin-user-is-admin").checked = user.isAdmin;
  openModal("admin-user-modal");
}

async function handleAdminUserSubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const userId = document.getElementById("admin-user-id").value;
    const fullName = document.getElementById("admin-user-name").value.trim();
    const billing = document.getElementById("admin-user-billing").value;
    const isAdmin = document.getElementById("admin-user-is-admin").checked;

    try {
      await updateAdminUser(supabase, userId, {
        fullName,
        billing,
        isAdmin
      });
      await hydrateAdminWorkspace();
      closeModal("admin-user-modal");
      showToast(t("toast_admin_user_updated"));
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

function updateCategoryMatches() {
  const name = document.getElementById("category-name").value.trim();
  const type = document.getElementById("category-type").value;
  const editId = document.getElementById("category-edit-id").value;
  const container = document.getElementById("category-matches");

  if (!name) {
    container.textContent = "";
    return;
  }

  const matches = getCategories(type)
    .filter((category) => {
      const customId = category.isDefault ? "" : category.id;
      return customId !== editId && normalizeForSearch(getCategoryDisplayName(category.key, type)).includes(normalizeForSearch(name));
    })
    .slice(0, 5);
  const exactMatch = findExactCategoryMatch(name, type, editId);

  if (!matches.length && !exactMatch) {
    container.textContent = "";
    return;
  }

  container.innerHTML = `
    <strong>${exactMatch ? t("category_match_exact") : t("category_matches")}</strong>
    <div class="category-match-list">
      ${matches.map((category) => `<span>${getCategoryDisplayName(category.key, type)}</span>`).join("")}
    </div>
  `;
}

function openBudgetModal() {
  document.getElementById("budget-form").reset();
  document.getElementById("budget-edit-category").value = "";
  populateCategorySelect("budget-cat", "expense");
  openModal("budget-modal");
}

function editBudget(category) {
  openBudgetModal();
  document.getElementById("budget-edit-category").value = category;
  document.getElementById("budget-cat").value = category;
  document.getElementById("budget-limit").value = state.budgets[category] || "";
}

async function handleBudgetSubmit(event) {
  event.preventDefault();

  const previousCategory = document.getElementById("budget-edit-category").value;
  const category = document.getElementById("budget-cat").value;
  const limit = Number.parseFloat(document.getElementById("budget-limit").value);

  if (!limit || limit <= 0) {
    showToast(t("error_enter_limit"), "error");
    return;
  }

  try {
    await saveBudget(supabase, state.user.id, category, limit, previousCategory);
    await hydrateWorkspace();
    closeModal("budget-modal");
    showToast(t("toast_budget_saved"));
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function deleteBudget(category) {
  if (!await confirmAction(t("confirm_delete_budget"), t("action_delete"))) {
    return;
  }

  try {
    await deleteBudgetByCategory(supabase, state.user.id, category);
    await hydrateWorkspace();
    renderBudget();
    showToast(t("toast_budget_deleted"), "info");
  } catch (error) {
    showToast(error.message, "error");
  }
}

function openAccountModal(accountId = "") {
  const form = document.getElementById("account-form");
  form.reset();
  populateSimpleOptions("account-type", ACCOUNT_TYPES.map((type) => ({ value: type, label: t(type === "savings" ? "account_savings" : "account_regular") })));
  populateSimpleOptions("account-currency", [
    { value: "", label: t("placeholder_select_currency") },
    ...SUPPORTED_CURRENCIES.map((currency) => ({ value: currency, label: currency }))
  ]);
  document.getElementById("account-modal-title").textContent = accountId ? t("modal_edit_account") : t("modal_new_account");
  document.getElementById("account-edit-id").value = accountId;
  document.getElementById("account-opening-balance").disabled = Boolean(accountId);
  document.getElementById("account-delete-button").classList.toggle("hidden", !accountId);

  if (!accountId) {
    document.getElementById("account-type").value = "savings";
    document.getElementById("account-currency").value = "";
    document.getElementById("account-include-total").checked = true;
  } else {
    const account = getAccountById(accountId);
    if (!account) {
      return;
    }

    document.getElementById("account-name").value = account.name;
    document.getElementById("account-type").value = account.type;
    document.getElementById("account-currency").value = account.currencyCode;
    document.getElementById("account-opening-balance").value = account.openingBalance;
    document.getElementById("account-include-total").checked = account.includeInTotal;
  }

  openModal("account-modal");
}

async function handleAccountSubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const accountId = document.getElementById("account-edit-id").value;
    const name = document.getElementById("account-name").value.trim();
    const type = document.getElementById("account-type").value;
    const currencyCode = document.getElementById("account-currency").value;
    const openingBalance = Number.parseFloat(document.getElementById("account-opening-balance").value) || 0;
    const includeInTotal = document.getElementById("account-include-total").checked;
    const linkedGoal = state.goals.find((goal) => goal.savingsAccountId === accountId);

    if (!name) {
      showToast(t("error_enter_account_name"), "error");
      return;
    }

    if (!currencyCode) {
      showToast(t("error_select_currency"), "error");
      return;
    }

    if (linkedGoal && linkedGoal.currencyCode !== currencyCode) {
      showToast(t("error_goal_currency_account_mismatch"), "error");
      return;
    }

    try {
      await saveAccount(supabase, state.user.id, {
        id: accountId || undefined,
        name,
        type,
        currencyCode,
        openingBalance: accountId ? getAccountById(accountId)?.openingBalance || 0 : openingBalance,
        includeInTotal
      });
      await hydrateWorkspace();
      closeModal("account-modal");
      showToast(t("toast_account_saved"));
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

async function deleteAccount(accountId) {
  if (!await confirmAction(t("confirm_delete_account"), t("action_delete"))) {
    return;
  }

  try {
    await deleteAccountById(supabase, state.user.id, accountId);
    await hydrateWorkspace();
    showToast(t("toast_account_deleted"), "info");
  } catch (error) {
    showToast(error.message, "error");
  }
}

function openTransferModal(toAccountId = "", fromAccountId = "") {
  const form = document.getElementById("transfer-form");
  form.reset();
  populateAccountSelect("transfer-from-account", state.accounts, fromAccountId || getDefaultRegularAccountId());
  populateAccountSelect("transfer-to-account", state.accounts, toAccountId);
  document.getElementById("transfer-date").value = new Date().toISOString().split("T")[0];
  openModal("transfer-modal");
}

async function handleTransferSubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const fromAccountId = document.getElementById("transfer-from-account").value;
    const toAccountId = document.getElementById("transfer-to-account").value;
    const amount = Number.parseFloat(document.getElementById("transfer-amount-out").value);
    const convertedAmount = Number.parseFloat(document.getElementById("transfer-amount-in").value);
    const date = document.getElementById("transfer-date").value;
    const desc = document.getElementById("transfer-comment").value.trim();
    const manualRate = Number.parseFloat(document.getElementById("transfer-rate").value);
    const fromAccount = getAccountById(fromAccountId);
    const toAccount = getAccountById(toAccountId);

    if (!fromAccount || !toAccount) {
      showToast(t("error_select_transfer_accounts"), "error");
      return;
    }

    if (fromAccountId === toAccountId) {
      showToast(t("error_select_different_accounts"), "error");
      return;
    }

    if (!amount || amount <= 0 || !convertedAmount || convertedAmount <= 0) {
      showToast(t("error_enter_transfer_amount"), "error");
      return;
    }

    if (fromAccount.currencyCode === toAccount.currencyCode && amount !== convertedAmount) {
      showToast(t("error_same_currency_transfer"), "error");
      return;
    }

    try {
      await createTransaction(supabase, state.user.id, {
        type: fromAccount.currencyCode === toAccount.currencyCode ? "transfer" : "exchange",
        fromAccountId,
        toAccountId,
        amount,
        convertedAmount,
        exchangeRate: manualRate || null,
        date,
        desc,
        currencyCode: fromAccount.currencyCode,
        convertedCurrencyCode: toAccount.currencyCode
      });
      await hydrateWorkspace();
      closeModal("transfer-modal");
      showToast(t("toast_transfer_saved"));
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

function openAccountExpenseModal(accountId = "") {
  const form = document.getElementById("account-expense-form");
  form.reset();
  populateAccountSelect("account-expense-account", state.accounts, accountId);
  populateCategorySelect("account-expense-category", "expense");
  document.getElementById("account-expense-date").value = new Date().toISOString().split("T")[0];
  openModal("account-expense-modal");
}

async function handleAccountExpenseSubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const accountId = document.getElementById("account-expense-account").value;
    const amount = Number.parseFloat(document.getElementById("account-expense-amount").value);
    const date = document.getElementById("account-expense-date").value;
    const category = document.getElementById("account-expense-category").value;
    const desc = document.getElementById("account-expense-comment").value.trim();
    const account = getAccountById(accountId);

    if (!account) {
      showToast(t("error_select_account"), "error");
      return;
    }

    if (!amount || amount <= 0) {
      showToast(t("error_invalid_amount"), "error");
      return;
    }

    try {
      await createTransaction(supabase, state.user.id, {
        type: "expense",
        accountId,
        amount,
        date,
        category,
        desc,
        currencyCode: account.currencyCode
      });
      await hydrateWorkspace();
      closeModal("account-expense-modal");
      showToast(t("toast_account_expense_saved"));
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

function openGoalModal(goalId = "") {
  const form = document.getElementById("goal-form");
  form.reset();
  document.getElementById("goal-icon").value = "🎯";
  document.getElementById("goal-edit-id").value = goalId;
  document.getElementById("goal-modal-title").textContent = goalId ? t("modal_edit_goal") : t("modal_new_goal");
  populateSimpleOptions("goal-currency", SUPPORTED_CURRENCIES.map((currency) => ({ value: currency, label: currency })));

  if (goalId) {
    const goal = state.goals.find((item) => item.id === goalId);
    if (goal) {
      document.getElementById("goal-name").value = goal.name;
      document.getElementById("goal-target").value = goal.target;
      document.getElementById("goal-icon").value = goal.icon || "🎯";
      document.getElementById("goal-deadline").value = goal.deadline || "";
      document.getElementById("goal-currency").value = goal.currencyCode;
    }
  } else {
    document.getElementById("goal-currency").value = state.currency;
  }

  syncGoalAccountOptions(goalId ? state.goals.find((item) => item.id === goalId)?.savingsAccountId || "" : "");
  openModal("goal-modal");
}

function syncGoalAccountOptions(selectedAccountId = "") {
  const currencyCode = document.getElementById("goal-currency").value;
  const savingsAccounts = getSavingsAccounts().filter((account) => account.currencyCode === currencyCode);
  const existingGoalId = document.getElementById("goal-edit-id").value;

  const options = [{ value: "", label: t("goal_no_account") }]
    .concat(savingsAccounts.map((account) => ({ value: account.id, label: `${account.name} (${account.currencyCode})` })));

  populateSimpleOptions("goal-account", options);

  if (selectedAccountId && savingsAccounts.some((account) => account.id === selectedAccountId)) {
    document.getElementById("goal-account").value = selectedAccountId;
    return;
  }

  if (existingGoalId) {
    const goal = state.goals.find((item) => item.id === existingGoalId);
    if (goal?.savingsAccountId && savingsAccounts.some((account) => account.id === goal.savingsAccountId)) {
      document.getElementById("goal-account").value = goal.savingsAccountId;
    }
  }
}

async function handleGoalSubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const goalId = document.getElementById("goal-edit-id").value;
    const existingGoal = state.goals.find((item) => item.id === goalId);
    const name = document.getElementById("goal-name").value.trim();
    const target = Number.parseFloat(document.getElementById("goal-target").value);
    const icon = document.getElementById("goal-icon").value || "🎯";
    const deadline = document.getElementById("goal-deadline").value;
    const currencyCode = document.getElementById("goal-currency").value;
    const savingsAccountId = document.getElementById("goal-account").value;
    const linkedAccount = savingsAccountId ? getAccountById(savingsAccountId) : null;
    const saved = existingGoal?.saved || 0;

    if (!name) {
      showToast(t("error_enter_goal_name"), "error");
      return;
    }

    if (!target || target <= 0) {
      showToast(t("error_enter_goal_target"), "error");
      return;
    }

    if (linkedAccount && linkedAccount.currencyCode !== currencyCode) {
      showToast(t("error_goal_currency_account_mismatch"), "error");
      return;
    }

    const computedSaved = linkedAccount ? getAccountBalance(linkedAccount.id) : saved;
    const computedFunded = existingGoal ? getGoalFundedAmount({ ...existingGoal, savingsAccountId }) : computedSaved;
    const computedSpent = existingGoal ? getGoalSpentAmount(existingGoal.id) : 0;
    const status = existingGoal?.status === "spent" || existingGoal?.status === "cancelled"
      ? existingGoal.status
      : computedSpent >= target ? "spent"
        : computedFunded >= target ? "reached" : "active";

    try {
      await saveGoal(supabase, state.user.id, {
        id: goalId || undefined,
        name,
        target,
        saved,
        icon,
        deadline,
        currencyCode,
        savingsAccountId,
        status,
        completedAt: status === "spent" ? existingGoal?.completedAt || new Date().toISOString() : null
      });
      await hydrateWorkspace();
      closeModal("goal-modal");
      showToast(goalId ? t("toast_goal_updated") : t("toast_goal_added"));
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

async function deleteGoal(goalId) {
  if (!await confirmAction(t("confirm_delete_goal"), t("action_delete"))) {
    return;
  }

  try {
    await deleteGoalById(supabase, state.user.id, goalId);
    await hydrateWorkspace();
    showToast(t("toast_goal_deleted"), "info");
  } catch (error) {
    showToast(error.message, "error");
  }
}

function openGoalSpendModal(goalId) {
  const goal = state.goals.find((item) => item.id === goalId);
  if (!goal?.savingsAccountId) {
    showToast(t("error_goal_requires_account"), "error");
    return;
  }

  const account = getAccountById(goal.savingsAccountId);
  if (!account) {
    showToast(t("error_select_account"), "error");
    return;
  }

  document.getElementById("goal-spend-form").reset();
  document.getElementById("goal-spend-goal-id").value = goal.id;
  populateAccountSelect("goal-spend-account", [account], account.id);
  document.getElementById("goal-spend-balance").value = formatMoney(getAccountBalance(account.id), account.currencyCode);
  document.getElementById("goal-spend-date").value = new Date().toISOString().split("T")[0];
  openModal("goal-spend-modal");
}

async function handleGoalSpendSubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const goalId = document.getElementById("goal-spend-goal-id").value;
    const goal = state.goals.find((item) => item.id === goalId);
    const account = goal?.savingsAccountId ? getAccountById(goal.savingsAccountId) : null;
    const amount = Number.parseFloat(document.getElementById("goal-spend-amount").value);
    const date = document.getElementById("goal-spend-date").value;
    const desc = document.getElementById("goal-spend-comment").value.trim();
    const markCompleted = document.getElementById("goal-spend-complete").checked;

    if (!goal || !account) {
      showToast(t("error_goal_requires_account"), "error");
      return;
    }

    if (!amount || amount <= 0) {
      showToast(t("error_invalid_amount"), "error");
      return;
    }

    try {
      await createTransaction(supabase, state.user.id, {
        type: "expense",
        accountId: account.id,
        amount,
        date,
        category: "goal_expense",
        desc: desc || goal.name,
        currencyCode: account.currencyCode,
        goalId: goal.id
      });

      const spentAfter = getGoalSpentAmount(goal.id) + amount;
      const nextStatus = markCompleted || spentAfter >= goal.target
        ? "spent"
        : getGoalComputedStatus(goal);

      if (nextStatus !== goal.status) {
        await saveGoal(supabase, state.user.id, {
          ...goal,
          status: nextStatus,
          completedAt: nextStatus === "spent" ? new Date().toISOString() : goal.completedAt
        });
      }

      await hydrateWorkspace();
      closeModal("goal-spend-modal");
      showToast(t("toast_goal_spend_saved"));
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

function openLiabilityModal(liabilityId = "") {
  const form = document.getElementById("liability-form");
  form.reset();
  document.getElementById("liability-edit-id").value = liabilityId;
  document.getElementById("liability-modal-title").textContent = liabilityId ? t("modal_edit_liability") : t("modal_new_liability");
  populateSimpleOptions("liability-currency", SUPPORTED_CURRENCIES.map((currency) => ({ value: currency, label: currency })));
  populateSimpleOptions("liability-type", LIABILITY_TYPES.map((type) => ({ value: type, label: t(`liability_type_${type}`) })));

  if (liabilityId) {
    const liability = state.liabilities.find((item) => item.id === liabilityId);
    if (!liability || liability.status === "settled") {
      return;
    }

    document.getElementById("liability-counterparty").value = liability.counterpartyName;
    document.getElementById("liability-amount").value = liability.amount;
    document.getElementById("liability-currency").value = liability.currencyCode;
    document.getElementById("liability-type").value = liability.type;
    document.getElementById("liability-due-date").value = liability.dueDate || "";
    document.getElementById("liability-comment").value = liability.comment || "";
  } else {
    document.getElementById("liability-currency").value = state.currency;
    document.getElementById("liability-type").value = "receivable";
  }

  openModal("liability-modal");
}

async function handleLiabilitySubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const liabilityId = document.getElementById("liability-edit-id").value;
    const existingLiability = state.liabilities.find((item) => item.id === liabilityId);
    const counterpartyName = document.getElementById("liability-counterparty").value.trim();
    const amount = Number.parseFloat(document.getElementById("liability-amount").value);
    const currencyCode = document.getElementById("liability-currency").value;
    const type = document.getElementById("liability-type").value;
    const dueDate = document.getElementById("liability-due-date").value;
    const comment = document.getElementById("liability-comment").value.trim();

    if (!counterpartyName) {
      showToast(t("error_enter_counterparty"), "error");
      return;
    }

    if (!amount || amount <= 0) {
      showToast(t("error_invalid_amount"), "error");
      return;
    }

    try {
      await saveLiability(supabase, state.user.id, {
        id: liabilityId || undefined,
        counterpartyName,
        amount,
        currencyCode,
        type,
        dueDate,
        comment,
        status: existingLiability?.status || "open"
      });
      await hydrateWorkspace();
      closeModal("liability-modal");
      showToast(liabilityId ? t("toast_liability_updated") : t("toast_liability_added"));
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

function openLiabilitySettleModal(liabilityId) {
  const liability = state.liabilities.find((item) => item.id === liabilityId);
  if (!liability || liability.status === "settled") {
    return;
  }

  const accounts = state.accounts.filter((account) => account.currencyCode === liability.currencyCode);
  if (!accounts.length) {
    showToast(t("error_select_account"), "error");
    return;
  }

  document.getElementById("liability-settle-form").reset();
  document.getElementById("liability-settle-id").value = liability.id;
  document.getElementById("liability-settle-prompt").textContent = liability.type === "receivable"
    ? t("settle_receivable_account_prompt")
    : t("settle_payable_account_prompt");
  document.getElementById("liability-settle-amount").value = formatMoney(liability.amount, liability.currencyCode);
  document.getElementById("liability-settle-date").value = new Date().toISOString().split("T")[0];
  populateAccountSelect("liability-settle-account", accounts, accounts[0]?.id || "");
  openModal("liability-settle-modal");
}

async function handleLiabilitySettleSubmit(event) {
  event.preventDefault();

  await runWithSubmitLock(event.currentTarget, async () => {
    const liabilityId = document.getElementById("liability-settle-id").value;
    const accountId = document.getElementById("liability-settle-account").value;
    const date = document.getElementById("liability-settle-date").value;
    const liability = state.liabilities.find((item) => item.id === liabilityId);
    const account = getAccountById(accountId);

    if (!liability || liability.status === "settled") {
      return;
    }

    if (!account) {
      showToast(t("error_select_account"), "error");
      return;
    }

    if (account.currencyCode !== liability.currencyCode) {
      showToast(t("error_liability_currency_account_mismatch"), "error");
      return;
    }

    if (!date) {
      showToast(t("error_choose_date"), "error");
      return;
    }

    const transactionType = liability.type === "receivable" ? "income" : "expense";
    const category = transactionType === "income" ? "other_income" : "other_expense";
    const desc = `${t("action_settle")}: ${liability.counterpartyName}${liability.comment ? ` - ${liability.comment}` : ""}`;

    try {
      const transaction = await createTransaction(supabase, state.user.id, {
        type: transactionType,
        accountId,
        amount: liability.amount,
        date,
        category,
        desc,
        currencyCode: account.currencyCode
      });

      await settleLiability(supabase, state.user.id, liability.id, {
        accountId,
        transactionId: transaction.id,
        settledAt: new Date().toISOString()
      });
      await hydrateWorkspace();
      closeModal("liability-settle-modal");
      showToast(t("toast_liability_settled"));
    } catch (error) {
      showToast(error.message, "error");
    }
  });
}

async function deleteLiability(liabilityId) {
  if (!await confirmAction(t("confirm_delete_liability"), t("action_delete"))) {
    return;
  }

  try {
    await deleteLiabilityById(supabase, state.user.id, liabilityId);
    await hydrateWorkspace();
    showToast(t("toast_liability_deleted"), "info");
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function handleExport() {
  try {
    const payload = await exportUserData(supabase, state.user.id);
    downloadJson(payload, `norocel-backup-${new Date().toISOString().slice(0, 10)}.json`);
    showToast(t("toast_data_exported"));
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function handleImport(event) {
  const file = event.target.files[0];
  if (!file) {
    return;
  }

  try {
    const payload = await readJsonFile(file);
    await importUserData(supabase, state.user.id, payload);
    await hydrateWorkspace();
    showToast(t("toast_data_imported"));
  } catch (error) {
    showToast(error.message, "error");
  } finally {
    event.target.value = "";
  }
}

async function handleLogout() {
  if (!await confirmAction(t("confirm_logout"), t("logout"))) {
    return;
  }

  try {
    await signOut(supabase);
    showToast(t("toast_session_ended"), "info");
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function handleLanguageChange(language) {
  state.language = language;
  state.openMenu = null;
  persistLanguage();
  renderStaticTexts();
  renderUserHeader();
  renderCurrentPage();

  if (state.user) {
    await persistPreferences();
  }
}

async function handleCurrencyChange(currency) {
  state.currency = currency;
  state.openMenu = null;
  renderCurrentPage();
  renderPreferenceSelectors();

  if (state.user) {
    await persistPreferences();
  }
}

function handleThemeChange(theme) {
  state.theme = theme;
  state.openMenu = null;
  applyTheme(theme);
  persistTheme();
  renderPreferenceSelectors();
  renderUserHeader();
  renderCurrentPage();
}

async function persistPreferences() {
  try {
    await updateProfilePreferences(supabase, state.user.id, {
      language: state.language,
      currency: state.currency
    });
  } catch (error) {
    showToast(error.message, "error");
  }
}

function persistTheme() {
  try {
    window.localStorage.setItem("norocel-theme", state.theme);
  } catch {
    // Ignore storage failures and keep the current in-memory theme.
  }
}

function persistLanguage() {
  try {
    window.localStorage.setItem("norocel-language", state.language);
  } catch {
    // Ignore storage failures and keep the current in-memory language.
  }
}

async function handleSettingsSubmit(event) {
  event.preventDefault();

  const fullName = document.getElementById("settings-name").value.trim();
  const email = document.getElementById("settings-email").value.trim();
  const avatarUrl = document.getElementById("settings-avatar").value.trim();

  try {
    await updateUserProfile(supabase, {
      fullName,
      email,
      avatarUrl
    });
    await updateProfileDetails(supabase, state.user.id, {
      full_name: fullName || email.split("@")[0] || null,
      email,
      avatar_url: avatarUrl || null
    });
    await hydrateWorkspace();
    showToast(t("toast_profile_saved"));
  } catch (error) {
    showToast(error.message, "error");
  }
}

function handleAvatarPreview() {
  const avatar = document.getElementById("settings-avatar-preview");
  const name = document.getElementById("settings-name").value.trim() || state.profile?.full_name || state.user?.email || t("user_fallback");
  const avatarUrl = document.getElementById("settings-avatar").value.trim();

  avatar.style.backgroundImage = avatarUrl ? `linear-gradient(var(--avatar-image-tint), var(--avatar-image-tint)), url("${avatarUrl}")` : "";
  avatar.style.backgroundSize = avatarUrl ? "cover" : "";
  avatar.style.backgroundPosition = avatarUrl ? "center" : "";
  avatar.textContent = avatarUrl ? "" : name.slice(0, 1).toUpperCase();
}

function toggleMenu(menuId) {
  state.openMenu = state.openMenu === menuId ? null : menuId;
  renderPreferenceSelectors();
}

function runAction(action, dataset) {
  if (action === "edit-transaction") {
    editTransaction(dataset.id);
  } else if (action === "edit-budget") {
    editBudget(dataset.category);
  } else if (action === "delete-transaction") {
    deleteTransaction(dataset.id);
  } else if (action === "delete-budget") {
    deleteBudget(dataset.category);
  } else if (action === "quick-add-category") {
    openQuickCategoryModal(dataset);
  } else if (action === "edit-category") {
    openCategoryModal({ categoryId: dataset.id });
  } else if (action === "delete-category") {
    deleteCategory(dataset.id);
  } else if (action === "edit-admin-user") {
    openAdminUserModal(dataset.id);
  } else if (action === "edit-goal") {
    openGoalModal(dataset.id);
  } else if (action === "delete-goal") {
    deleteGoal(dataset.id);
  } else if (action === "edit-liability") {
    openLiabilityModal(dataset.id);
  } else if (action === "delete-liability") {
    deleteLiability(dataset.id);
  } else if (action === "settle-liability") {
    openLiabilitySettleModal(dataset.id);
  } else if (action === "fund-account") {
    openTransferModal(dataset.id);
  } else if (action === "withdraw-account") {
    openAccountExpenseModal(dataset.id);
  } else if (action === "view-account-history") {
    state.activeAccountId = dataset.id;
    renderCurrentPage();
  } else if (action === "edit-account") {
    openAccountModal(dataset.id);
  } else if (action === "delete-account") {
    deleteAccount(dataset.id);
  } else if (action === "spend-goal") {
    openGoalSpendModal(dataset.id);
  }
}

function showAuth() {
  document.getElementById("auth-shell").classList.remove("hidden");
  document.getElementById("app-shell").classList.add("hidden");
}

function showApp() {
  document.getElementById("auth-shell").classList.add("hidden");
  document.getElementById("app-shell").classList.remove("hidden");
}

function finishBoot() {
  document.body.classList.remove("app-booting");
}

function disableAuth(message) {
  const authMessage = document.getElementById("auth-message");
  authMessage.textContent = message;
  authMessage.classList.remove("hidden");
  document.getElementById("auth-submit").disabled = true;
  document.getElementById("auth-email").disabled = true;
  document.getElementById("auth-password").disabled = true;
  document.getElementById("auth-name").disabled = true;
}

function openModal(id) {
  document.getElementById(id).classList.add("open");
}

function closeModal(id) {
  if (id === "confirm-modal" && confirmResolver) {
    resolveConfirm(false);
    return;
  }

  document.getElementById(id).classList.remove("open");
}

function handleConfirmSubmit(event) {
  event.preventDefault();
  resolveConfirm(true);
}

function bindThemeMedia() {
  const media = window.matchMedia("(prefers-color-scheme: dark)");
  media.addEventListener("change", () => {
    if (state.theme === "system") {
      applyTheme("system");
      renderUserHeader();
      renderCurrentPage();
      renderPreferenceSelectors();
    }
  });
}

function applyTheme(theme) {
  const resolvedTheme = theme === "system"
    ? (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light")
    : theme;

  document.documentElement.dataset.theme = resolvedTheme;
  document.documentElement.dataset.themePreference = theme;
}

function closeSidebarOnMobile() {
  if (window.innerWidth < 900) {
    document.getElementById("sidebar").classList.remove("open");
  }
}

function readJsonFile(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      try {
        resolve(JSON.parse(reader.result));
      } catch {
        reject(new Error(t("error_read_file")));
      }
    };
    reader.onerror = () => reject(new Error(t("error_read_file")));
    reader.readAsText(file);
  });
}

function downloadJson(payload, filename) {
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

function populateAccountSelect(selectId, accounts, selectedValue = "", includeEmpty = false) {
  const select = document.getElementById(selectId);
  const options = [];

  if (includeEmpty) {
    options.push(`<option value="">${t("goal_no_account")}</option>`);
  }

  options.push(
    ...accounts.map((account) => `<option value="${account.id}">${account.name} (${account.currencyCode})</option>`)
  );

  select.innerHTML = options.join("");
  if (selectedValue) {
    select.value = selectedValue;
  }
}

function populateSimpleOptions(selectId, options) {
  document.getElementById(selectId).innerHTML = options
    .map((option) => `<option value="${option.value}">${option.label}</option>`)
    .join("");
}

function populateCategorySelect(selectId, type, selectedValue = "") {
  populateSimpleOptions(selectId, getCategories(type).map((category) => ({
    value: category.key,
    label: `${category.isDefault ? "" : "+ "}${getCategoryDisplayName(category.key, type)}`
  })));

  if (selectedValue) {
    document.getElementById(selectId).value = selectedValue;
  }
}

function refreshCategorySelect(selectId, type, selectedValue) {
  if (selectId === "tx-category") {
    setTransactionType(type);
    document.getElementById(selectId).value = selectedValue;
    return;
  }

  populateCategorySelect(selectId, type, selectedValue);
}

function getCategoryDisplayName(categoryKey, type = "") {
  const category = getCategoryByKey(categoryKey, type);
  if (!category) {
    return categoryKey;
  }

  return category.isDefault ? getCategoryLabel(category.key) : category.name;
}

function findExactCategoryMatch(name, type, ignoredCategoryId = "") {
  const normalizedName = normalizeForSearch(name);
  return getCategories(type).find((category) => {
    const customId = category.isDefault ? "" : category.id;
    return customId !== ignoredCategoryId && normalizeForSearch(getCategoryDisplayName(category.key, type)) === normalizedName;
  });
}

function createCategoryKey(name, type) {
  const base = normalizeForKey(name) || "category";
  const existingKeys = new Set(getCategories(type).map((category) => category.key));
  let key = `custom_${base}`;
  let index = 2;

  while (existingKeys.has(key)) {
    key = `custom_${base}_${index}`;
    index += 1;
  }

  return key;
}

function normalizeForKey(value) {
  return value
    .trim()
    .toLowerCase()
    .normalize("NFKD")
    .replace(/\p{Mark}/gu, "")
    .replace(/[^\p{Letter}\p{Number}]+/gu, "_")
    .replace(/^_+|_+$/g, "");
}

function normalizeForSearch(value) {
  return value.trim().toLowerCase().replace(/\s+/g, " ");
}

function getDefaultRegularAccountId() {
  return state.accounts.find((account) => account.type === "regular" && account.currencyCode === state.currency)?.id
    || state.accounts.find((account) => account.type === "regular")?.id
    || state.accounts[0]?.id
    || "";
}

async function runWithSubmitLock(form, callback) {
  if (form.dataset.submitting === "true") {
    return;
  }

  form.dataset.submitting = "true";
  const submitButton = form.querySelector("[type='submit']");
  if (submitButton) {
    submitButton.disabled = true;
  }

  try {
    await callback();
  } finally {
    form.dataset.submitting = "false";
    if (submitButton) {
      submitButton.disabled = false;
    }
  }
}

function confirmAction(message, actionLabel) {
  document.getElementById("confirm-message").textContent = message;
  document.getElementById("confirm-action-button").textContent = actionLabel;
  openModal("confirm-modal");

  return new Promise((resolve) => {
    confirmResolver = resolve;
  });
}

function resolveConfirm(result) {
  const resolver = confirmResolver;
  confirmResolver = null;
  document.getElementById("confirm-modal").classList.remove("open");
  if (resolver) {
    resolver(result);
  }
}
