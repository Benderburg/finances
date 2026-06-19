import { DEFAULT_CURRENCY, DEFAULT_LANGUAGE, EXPENSE_CATEGORIES } from "./config.js";
import { state } from "./store.js";
import { createSupabaseClient, isSupabaseConfigured } from "./supabase.js";
import {
  createTransaction,
  deleteBudgetByCategory,
  deleteGoalById,
  deleteTransactionById,
  exportUserData,
  importUserData,
  loadUserWorkspace,
  saveBudget,
  saveGoal,
  saveProfile,
  saveTransaction,
  sendPasswordReset,
  signIn,
  signOut,
  signUp,
  updateProfileDetails,
  updateProfilePreferences,
  updateUserProfile
} from "./supabase-api.js";
import {
  renderAuthMode,
  renderBudget,
  renderCurrentPage,
  renderGoals,
  renderNavigation,
  renderPreferenceSelectors,
  renderStaticTexts,
  renderUserHeader,
  setTransactionType,
  showToast,
  t,
  updateMonthLabel
} from "./ui.js";

let supabase;

window.addEventListener("DOMContentLoaded", init);

async function init() {
  applyTheme(state.theme);
  bindThemeMedia();
  renderStaticTexts();

  if (!isSupabaseConfigured()) {
    disableAuth(t("error_setup_required"));
    bindStaticEvents();
    return;
  }

  supabase = createSupabaseClient();
  bindStaticEvents();

  const { data, error } = await supabase.auth.getSession();
  if (error) {
    showToast(error.message, "error");
  }

  await applySession(data.session);

  supabase.auth.onAuthStateChange(async (_event, session) => {
    await applySession(session);
  });
}

function bindStaticEvents() {
  document.addEventListener("click", handleDocumentClick);
  document.getElementById("auth-form").addEventListener("submit", handleAuthSubmit);
  document.getElementById("transaction-form").addEventListener("submit", handleTransactionSubmit);
  document.getElementById("budget-form").addEventListener("submit", handleBudgetSubmit);
  document.getElementById("goal-form").addEventListener("submit", handleGoalSubmit);
  document.getElementById("settings-form").addEventListener("submit", handleSettingsSubmit);
  document.getElementById("import-file").addEventListener("change", handleImport);
  document.getElementById("settings-name").addEventListener("input", handleAvatarPreview);
  document.getElementById("settings-avatar").addEventListener("input", handleAvatarPreview);
}

async function applySession(session) {
  if (!session?.user) {
    state.user = null;
    state.profile = null;
    state.transactions = [];
    state.budgets = {};
    state.goals = [];
    state.language = DEFAULT_LANGUAGE;
    state.currency = DEFAULT_CURRENCY;
    state.currentPage = "dashboard";
    state.openMenu = null;
    renderStaticTexts();
    showAuth();
    return;
  }

  state.user = session.user;

  try {
    await saveProfile(supabase, session.user);
    await hydrateWorkspace();
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
  state.transactions = workspace.transactions;
  state.budgets = workspace.budgets;
  state.goals = workspace.goals;

  renderStaticTexts();
  renderUserHeader();
  updateMonthLabel();
  renderNavigation();
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
    renderAuthMode();
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

  if (event.target.id === "prev-month") {
    changeMonth(-1);
    return;
  }

  if (event.target.id === "next-month") {
    changeMonth(1);
    return;
  }

  if (event.target.id === "sidebar-toggle") {
    document.getElementById("sidebar").classList.toggle("open");
    return;
  }

  if (event.target.id === "add-transaction-button" || event.target.id === "quick-income") {
    openTransactionModal("income");
    return;
  }

  if (event.target.id === "quick-expense") {
    openTransactionModal("expense");
    return;
  }

  if (event.target.id === "add-budget-button") {
    openBudgetModal();
    return;
  }

  if (event.target.id === "add-goal-button") {
    openGoalModal();
    return;
  }

  if (event.target.id === "logout-button") {
    handleLogout();
    return;
  }

  if (event.target.id === "export-button") {
    handleExport();
    return;
  }

  const actionButton = event.target.closest("[data-action]");
  if (actionButton) {
    runAction(actionButton.dataset.action, actionButton.dataset);
  }
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
      await signUp(supabase, email, password, fullName);
      showToast(t("toast_signup_success"));
      return;
    }

    await sendPasswordReset(supabase, email);
    showToast(t("toast_reset_sent"));
  } catch (error) {
    showToast(error.message, "error");
  }
}

function openTransactionModal(type = "income") {
  document.getElementById("tx-edit-id").value = "";
  document.getElementById("modal-title").textContent = t("modal_new_transaction");
  document.getElementById("transaction-form").reset();
  document.getElementById("tx-date").value = new Date().toISOString().split("T")[0];
  setTransactionType(type);
  openModal("add-modal");
}

function editTransaction(transactionId) {
  const transaction = state.transactions.find((item) => item.id === transactionId);
  if (!transaction) {
    return;
  }

  document.getElementById("tx-edit-id").value = transaction.id;
  document.getElementById("modal-title").textContent = t("modal_edit_transaction");
  document.getElementById("tx-amount").value = transaction.amount;
  document.getElementById("tx-desc").value = transaction.desc || "";
  document.getElementById("tx-date").value = transaction.date;
  setTransactionType(transaction.type);
  document.getElementById("tx-category").value = transaction.category;
  openModal("add-modal");
}

async function handleTransactionSubmit(event) {
  event.preventDefault();

  const amount = Number.parseFloat(document.getElementById("tx-amount").value);
  const description = document.getElementById("tx-desc").value.trim();
  const date = document.getElementById("tx-date").value;
  const category = document.getElementById("tx-category").value;
  const type = document.getElementById("tx-type").value;
  const editId = document.getElementById("tx-edit-id").value;

  if (!amount || amount <= 0) {
    showToast(t("error_invalid_amount"), "error");
    return;
  }

  if (!date) {
    showToast(t("error_choose_date"), "error");
    return;
  }

  try {
    if (editId) {
      await saveTransaction(supabase, state.user.id, { id: editId, amount, desc: description, date, category, type });
      showToast(t("toast_tx_updated"));
    } else {
      await createTransaction(supabase, state.user.id, { amount, desc: description, date, category, type });
      showToast(t("toast_tx_added"));
    }

    await hydrateWorkspace();
    closeModal("add-modal");
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function deleteTransaction(transactionId) {
  if (!window.confirm(t("confirm_delete_transaction"))) {
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

function openBudgetModal() {
  document.getElementById("budget-form").reset();
  const select = document.getElementById("budget-cat");
  select.innerHTML = EXPENSE_CATEGORIES
    .map((category) => `<option value="${category}">${t(`category_${category}`)}</option>`)
    .join("");
  openModal("budget-modal");
}

async function handleBudgetSubmit(event) {
  event.preventDefault();

  const category = document.getElementById("budget-cat").value;
  const limit = Number.parseFloat(document.getElementById("budget-limit").value);

  if (!limit || limit <= 0) {
    showToast(t("error_enter_limit"), "error");
    return;
  }

  try {
    await saveBudget(supabase, state.user.id, category, limit);
    await hydrateWorkspace();
    closeModal("budget-modal");
    showToast(t("toast_budget_saved"));
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function deleteBudget(category) {
  try {
    await deleteBudgetByCategory(supabase, state.user.id, category);
    await hydrateWorkspace();
    renderBudget();
    showToast(t("toast_budget_deleted"), "info");
  } catch (error) {
    showToast(error.message, "error");
  }
}

function openGoalModal(goalId = "") {
  const form = document.getElementById("goal-form");
  form.reset();
  document.getElementById("goal-icon").value = "🎯";
  document.getElementById("goal-edit-id").value = goalId;
  document.getElementById("goal-modal-title").textContent = goalId ? t("modal_edit_goal") : t("modal_new_goal");

  if (goalId) {
    const goal = state.goals.find((item) => item.id === goalId);
    if (goal) {
      document.getElementById("goal-name").value = goal.name;
      document.getElementById("goal-target").value = goal.target;
      document.getElementById("goal-saved").value = goal.saved;
      document.getElementById("goal-icon").value = goal.icon || "🎯";
      document.getElementById("goal-deadline").value = goal.deadline || "";
    }
  }

  openModal("goal-modal");
}

async function handleGoalSubmit(event) {
  event.preventDefault();

  const name = document.getElementById("goal-name").value.trim();
  const target = Number.parseFloat(document.getElementById("goal-target").value);
  const saved = Number.parseFloat(document.getElementById("goal-saved").value) || 0;
  const icon = document.getElementById("goal-icon").value || "🎯";
  const deadline = document.getElementById("goal-deadline").value;
  const editId = document.getElementById("goal-edit-id").value;

  if (!name) {
    showToast(t("error_enter_goal_name"), "error");
    return;
  }

  if (!target || target <= 0) {
    showToast(t("error_enter_goal_target"), "error");
    return;
  }

  try {
    await saveGoal(supabase, state.user.id, { id: editId || undefined, name, target, saved, icon, deadline });
    await hydrateWorkspace();
    closeModal("goal-modal");
    showToast(editId ? t("toast_goal_updated") : t("toast_goal_added"));
  } catch (error) {
    showToast(error.message, "error");
  }
}

async function deleteGoal(goalId) {
  if (!window.confirm(t("confirm_delete_goal"))) {
    return;
  }

  try {
    await deleteGoalById(supabase, state.user.id, goalId);
    await hydrateWorkspace();
    renderGoals();
    showToast(t("toast_goal_deleted"), "info");
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
  if (!window.confirm(t("confirm_logout"))) {
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
  } else if (action === "delete-transaction") {
    deleteTransaction(dataset.id);
  } else if (action === "delete-budget") {
    deleteBudget(dataset.category);
  } else if (action === "edit-goal") {
    openGoalModal(dataset.id);
  } else if (action === "delete-goal") {
    deleteGoal(dataset.id);
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

function disableAuth(message) {
  document.getElementById("setup-note").textContent = message;
  document.getElementById("auth-submit").disabled = true;
  document.getElementById("auth-email").disabled = true;
  document.getElementById("auth-password").disabled = true;
  document.getElementById("auth-name").disabled = true;
}

function openModal(id) {
  document.getElementById(id).classList.add("open");
}

function closeModal(id) {
  document.getElementById(id).classList.remove("open");
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

function downloadJson(payload, fileName) {
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = fileName;
  link.click();
}

function readJsonFile(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = ({ target }) => {
      try {
        resolve(JSON.parse(target.result));
      } catch (error) {
        reject(error);
      }
    };
    reader.onerror = () => reject(new Error(t("error_read_file")));
    reader.readAsText(file);
  });
}
