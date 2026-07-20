import { normalizeCategory } from "./i18n.js?v=20260720-2";

export async function signIn(supabase, email, password) {
  const { error } = await supabase.auth.signInWithPassword({ email, password });
  if (error) {
    throw error;
  }
}

export async function signUp(supabase, email, password, fullName, language) {
  const { error } = await supabase.auth.signUp({
    email,
    password,
    options: {
      data: {
        full_name: fullName || null,
        language: language || null
      }
    }
  });

  if (error) {
    throw error;
  }
}

export async function signOut(supabase) {
  const { error } = await supabase.auth.signOut();
  if (error) {
    throw error;
  }
}

export async function sendPasswordReset(supabase, email) {
  const { error } = await supabase.auth.resetPasswordForEmail(email, {
    redirectTo: window.location.origin
  });

  if (error) {
    throw error;
  }
}

export async function saveProfile(supabase, user, preferences = {}) {
  const currentProfile = await supabase
    .from("profiles")
    .select("language,currency")
    .eq("id", user.id)
    .maybeSingle();

  if (currentProfile.error) {
    throw currentProfile.error;
  }

  const payload = {
    id: user.id,
    email: user.email,
    full_name: user.user_metadata?.full_name || user.email?.split("@")[0] || "User",
    avatar_url: user.user_metadata?.avatar_url || null,
    last_seen_at: new Date().toISOString()
  };
  const preferredLanguage = preferences.language || user.user_metadata?.language;
  const preferredCurrency = preferences.currency || user.user_metadata?.currency;

  if (!currentProfile.data) {
    payload.language = preferredLanguage;
    payload.currency = preferredCurrency;
  } else {
    if (!currentProfile.data.language && preferredLanguage) {
      payload.language = preferredLanguage;
    }

    if (!currentProfile.data.currency && preferredCurrency) {
      payload.currency = preferredCurrency;
    }
  }

  const query = currentProfile.data
    ? supabase.from("profiles").update(payload).eq("id", user.id)
    : supabase.from("profiles").insert(payload);

  const { error } = await query;
  if (error) {
    throw error;
  }
}

export async function loadAdminWorkspace(supabase) {
  const { data, error } = await supabase
    .from("profiles")
    .select("*")
    .order("created_at", { ascending: false });

  if (error) {
    throw error;
  }

  const users = (data || []).map(mapProfileFromDb);
  return {
    users,
    stats: getAdminStats(users)
  };
}

export async function updateAdminUser(supabase, userId, payload) {
  const { error } = await supabase
    .from("profiles")
    .update({
      full_name: payload.fullName,
      billing: payload.billing,
      is_admin: payload.isAdmin
    })
    .eq("id", userId);

  if (error) {
    throw error;
  }
}

export async function updateProfilePreferences(supabase, userId, payload) {
  const { error } = await supabase
    .from("profiles")
    .update(payload)
    .eq("id", userId);

  if (error) {
    throw error;
  }
}

export async function updateUserProfile(supabase, payload) {
  const updates = {};

  if (payload.email) {
    updates.email = payload.email;
  }

  if (payload.fullName !== undefined || payload.avatarUrl !== undefined) {
    updates.data = {};

    if (payload.fullName !== undefined) {
      updates.data.full_name = payload.fullName;
    }

    if (payload.avatarUrl !== undefined) {
      updates.data.avatar_url = payload.avatarUrl || null;
    }
  }

  if (!Object.keys(updates).length) {
    return;
  }

  const { error } = await supabase.auth.updateUser(updates);
  if (error) {
    throw error;
  }
}

export async function updateProfileDetails(supabase, userId, payload) {
  const { error } = await supabase
    .from("profiles")
    .update(payload)
    .eq("id", userId);

  if (error) {
    throw error;
  }
}

export async function loadUserWorkspace(supabase, userId) {
  const [profileResult, accountsResult, categoriesResult, transactionsResult, budgetsResult, goalsResult, liabilitiesResult] = await Promise.all([
    supabase.from("profiles").select("*").eq("id", userId).maybeSingle(),
    supabase.from("accounts").select("*").eq("user_id", userId).order("created_at", { ascending: true }),
    supabase.from("categories").select("*").eq("user_id", userId).order("type", { ascending: true }).order("name", { ascending: true }),
    supabase.from("transactions").select("*").eq("user_id", userId).order("transaction_date", { ascending: false }).order("created_at", { ascending: false }),
    supabase.from("budgets").select("*").eq("user_id", userId).order("category", { ascending: true }),
    supabase.from("goals").select("*").eq("user_id", userId).order("created_at", { ascending: false }),
    supabase.from("liabilities").select("*").eq("user_id", userId).order("status", { ascending: true }).order("due_date", { ascending: true, nullsFirst: false }).order("created_at", { ascending: false })
  ]);

  throwIfError(profileResult.error);
  throwIfError(accountsResult.error);
  throwIfError(categoriesResult.error);
  throwIfError(transactionsResult.error);
  throwIfError(budgetsResult.error);
  throwIfError(goalsResult.error);
  throwIfError(liabilitiesResult.error);

  return {
    profile: profileResult.data,
    accounts: (accountsResult.data || []).map(mapAccountFromDb),
    categories: (categoriesResult.data || []).map(mapCategoryFromDb),
    transactions: (transactionsResult.data || []).map(mapTransactionFromDb),
    budgets: (budgetsResult.data || []).reduce((accumulator, item) => {
      accumulator[normalizeCategory(item.category)] = Number(item.monthly_limit);
      return accumulator;
    }, {}),
    goals: (goalsResult.data || []).map(mapGoalFromDb),
    liabilities: (liabilitiesResult.data || []).map(mapLiabilityFromDb)
  };
}

export async function createTransaction(supabase, userId, transaction) {
  const { data, error } = await supabase
    .from("transactions")
    .insert(mapTransactionToDb(userId, transaction))
    .select("*")
    .single();
  if (error) {
    throw error;
  }
  return mapTransactionFromDb(data);
}

export async function saveTransaction(supabase, userId, transaction) {
  const { error } = await supabase
    .from("transactions")
    .update(mapTransactionToDb(userId, transaction))
    .eq("id", transaction.id)
    .eq("user_id", userId);

  if (error) {
    throw error;
  }
}

export async function deleteTransactionById(supabase, userId, transactionId) {
  const { error } = await supabase.from("transactions").delete().eq("id", transactionId).eq("user_id", userId);
  if (error) {
    throw error;
  }
}

export async function saveBudget(supabase, userId, category, monthlyLimit, previousCategory = "") {
  if (previousCategory && previousCategory !== category) {
    const { error } = await supabase
      .from("budgets")
      .delete()
      .eq("user_id", userId)
      .eq("category", previousCategory);

    if (error) {
      throw error;
    }
  }

  const { error } = await supabase
    .from("budgets")
    .upsert(
      {
        user_id: userId,
        category,
        monthly_limit: monthlyLimit
      },
      { onConflict: "user_id,category" }
    );

  if (error) {
    throw error;
  }
}

export async function deleteBudgetByCategory(supabase, userId, category) {
  const { error } = await supabase.from("budgets").delete().eq("user_id", userId).eq("category", category);
  if (error) {
    throw error;
  }
}

export async function saveCategory(supabase, userId, category) {
  const payload = mapCategoryToDb(userId, category);

  if (category.id) {
    const { error } = await supabase
      .from("categories")
      .update({
        name: payload.name,
        type: payload.type
      })
      .eq("id", category.id)
      .eq("user_id", userId);

    if (error) {
      throw error;
    }

    return;
  }

  const { error } = await supabase.from("categories").insert(payload);
  if (error) {
    throw error;
  }
}

export async function deleteCategoryById(supabase, userId, categoryId) {
  const { error } = await supabase.from("categories").delete().eq("id", categoryId).eq("user_id", userId);
  if (error) {
    throw error;
  }
}

export async function saveAccount(supabase, userId, account) {
  const payload = mapAccountToDb(userId, account);

  if (account.id) {
    const { error } = await supabase
      .from("accounts")
      .update(payload)
      .eq("id", account.id)
      .eq("user_id", userId);

    if (error) {
      throw error;
    }

    return;
  }

  const { error } = await supabase.from("accounts").insert(payload);
  if (error) {
    throw error;
  }
}

export async function deleteAccountById(supabase, userId, accountId) {
  const { error } = await supabase.from("accounts").delete().eq("id", accountId).eq("user_id", userId);
  if (error) {
    throw error;
  }
}

export async function saveGoal(supabase, userId, goal) {
  const payload = mapGoalToDb(userId, goal);

  if (goal.id) {
    const { error } = await supabase
      .from("goals")
      .update(payload)
      .eq("id", goal.id)
      .eq("user_id", userId);

    if (error) {
      throw error;
    }

    return;
  }

  const { error } = await supabase.from("goals").insert(payload);
  if (error) {
    throw error;
  }
}

export async function deleteGoalById(supabase, userId, goalId) {
  const { error } = await supabase.from("goals").delete().eq("id", goalId).eq("user_id", userId);
  if (error) {
    throw error;
  }
}

export async function saveLiability(supabase, userId, liability) {
  const payload = mapLiabilityToDb(userId, liability);

  if (liability.id) {
    const { error } = await supabase
      .from("liabilities")
      .update(payload)
      .eq("id", liability.id)
      .eq("user_id", userId);

    if (error) {
      throw error;
    }

    return;
  }

  const { error } = await supabase.from("liabilities").insert(payload);
  if (error) {
    throw error;
  }
}

export async function settleLiability(supabase, userId, liabilityId, settlement) {
  const { error } = await supabase
    .from("liabilities")
    .update({
      status: "settled",
      settlement_account_id: settlement.accountId,
      settlement_transaction_id: settlement.transactionId,
      settled_at: settlement.settledAt || new Date().toISOString()
    })
    .eq("id", liabilityId)
    .eq("user_id", userId);

  if (error) {
    throw error;
  }
}

export async function deleteLiabilityById(supabase, userId, liabilityId) {
  const { error } = await supabase.from("liabilities").delete().eq("id", liabilityId).eq("user_id", userId);
  if (error) {
    throw error;
  }
}

export async function exportUserData(supabase, userId) {
  const workspace = await loadUserWorkspace(supabase, userId);
  return {
    profile: workspace.profile,
    accounts: workspace.accounts,
    categories: workspace.categories,
    transactions: workspace.transactions,
    budgets: workspace.budgets,
    goals: workspace.goals,
    liabilities: workspace.liabilities
  };
}

export async function importUserData(supabase, userId, payload) {
  const accounts = Array.isArray(payload.accounts) ? payload.accounts : [];
  const categories = Array.isArray(payload.categories) ? payload.categories : [];
  const transactions = Array.isArray(payload.transactions) ? payload.transactions : [];
  const goals = Array.isArray(payload.goals) ? payload.goals : [];
  const liabilities = Array.isArray(payload.liabilities) ? payload.liabilities : [];
  const budgets = payload.budgets && typeof payload.budgets === "object" ? payload.budgets : {};
  const profile = payload.profile && typeof payload.profile === "object" ? payload.profile : null;

  await Promise.all([
    supabase.from("transactions").delete().eq("user_id", userId),
    supabase.from("liabilities").delete().eq("user_id", userId),
    supabase.from("goals").delete().eq("user_id", userId),
    supabase.from("accounts").delete().eq("user_id", userId),
    supabase.from("categories").delete().eq("user_id", userId),
    supabase.from("budgets").delete().eq("user_id", userId)
  ]);

  if (categories.length) {
    const { error } = await supabase.from("categories").insert(
      categories.map((item) => mapCategoryToDb(userId, item, item.id))
    );
    if (error) {
      throw error;
    }
  }

  if (accounts.length) {
    const { error } = await supabase.from("accounts").insert(
      accounts.map((item) => mapAccountToDb(userId, item, item.id))
    );
    if (error) {
      throw error;
    }
  }

  if (goals.length) {
    const { error } = await supabase.from("goals").insert(
      goals.map((item) => mapGoalToDb(userId, item, item.id))
    );
    if (error) {
      throw error;
    }
  }

  if (transactions.length) {
    const { error } = await supabase.from("transactions").insert(
      transactions.map((item) => mapTransactionToDb(userId, item, item.id))
    );
    if (error) {
      throw error;
    }
  }

  if (liabilities.length) {
    const { error } = await supabase.from("liabilities").insert(
      liabilities.map((item) => mapLiabilityToDb(userId, item, item.id))
    );
    if (error) {
      throw error;
    }
  }

  const budgetRows = Object.entries(budgets).map(([category, monthlyLimit]) => ({
    user_id: userId,
    category: normalizeCategory(category),
    monthly_limit: monthlyLimit
  }));

  if (budgetRows.length) {
    const { error } = await supabase.from("budgets").insert(budgetRows);
    if (error) {
      throw error;
    }
  }

  if (profile) {
    const { error } = await supabase
      .from("profiles")
      .update({
        full_name: profile.full_name || null,
        language: profile.language || undefined,
        currency: profile.currency || undefined
      })
      .eq("id", userId);

    if (error) {
      throw error;
    }
  }
}

function mapAccountFromDb(item) {
  return {
    id: item.id,
    name: item.name,
    type: item.type,
    currencyCode: item.currency_code,
    openingBalance: Number(item.opening_balance || 0),
    includeInTotal: Boolean(item.include_in_total),
    createdAt: item.created_at
  };
}

function mapProfileFromDb(item) {
  return {
    id: item.id,
    email: item.email || "",
    fullName: item.full_name || "",
    avatarUrl: item.avatar_url || "",
    language: item.language || "ro",
    currency: item.currency || "MDL",
    billing: item.billing || "regular",
    isAdmin: Boolean(item.is_admin),
    createdAt: item.created_at,
    updatedAt: item.updated_at,
    lastSeenAt: item.last_seen_at || ""
  };
}

function getAdminStats(users) {
  const now = Date.now();
  const dayMs = 86400000;
  const returnedAfterDay = users.filter((user) => (
    user.lastSeenAt
    && new Date(user.lastSeenAt) - new Date(user.createdAt) >= dayMs
  )).length;
  const notReturned = users.filter((user) => (
    now - new Date(user.createdAt).getTime() >= dayMs
    && (!user.lastSeenAt || new Date(user.lastSeenAt) - new Date(user.createdAt) < dayMs)
  )).length;

  return {
    total: users.length,
    returnedAfterDay,
    notReturned,
    premium: users.filter((user) => user.billing === "premium").length,
    admins: users.filter((user) => user.isAdmin).length
  };
}

function mapAccountToDb(userId, account, forcedId) {
  return {
    ...(forcedId || account.id ? { id: forcedId || account.id } : {}),
    user_id: userId,
    name: account.name,
    type: account.type,
    currency_code: account.currencyCode,
    opening_balance: account.openingBalance || 0,
    include_in_total: account.includeInTotal !== false
  };
}

function mapCategoryFromDb(item) {
  return {
    id: item.id,
    key: item.key,
    name: item.name,
    type: item.type,
    createdAt: item.created_at
  };
}

function mapCategoryToDb(userId, category, forcedId) {
  return {
    ...(forcedId || category.id ? { id: forcedId || category.id } : {}),
    user_id: userId,
    key: category.key,
    name: category.name,
    type: category.type
  };
}

function mapTransactionFromDb(item) {
  return {
    id: item.id,
    amount: Number(item.amount),
    convertedAmount: item.converted_amount === null ? null : Number(item.converted_amount),
    exchangeRate: item.exchange_rate === null ? null : Number(item.exchange_rate),
    desc: item.description || "",
    date: item.transaction_date,
    category: item.category ? normalizeCategory(item.category) : "",
    type: item.type,
    accountId: item.account_id || "",
    fromAccountId: item.from_account_id || "",
    toAccountId: item.to_account_id || "",
    currencyCode: item.currency_code,
    convertedCurrencyCode: item.converted_currency_code || "",
    goalId: item.goal_id || ""
  };
}

function mapTransactionToDb(userId, transaction, forcedId) {
  return {
    ...(forcedId || transaction.id ? { id: forcedId || transaction.id } : {}),
    user_id: userId,
    amount: transaction.amount,
    converted_amount: transaction.convertedAmount ?? null,
    exchange_rate: transaction.exchangeRate ?? null,
    description: transaction.desc || null,
    transaction_date: transaction.date,
    category: transaction.category ? normalizeCategory(transaction.category) : null,
    type: transaction.type,
    account_id: transaction.accountId || null,
    from_account_id: transaction.fromAccountId || null,
    to_account_id: transaction.toAccountId || null,
    currency_code: transaction.currencyCode,
    converted_currency_code: transaction.convertedCurrencyCode || null,
    goal_id: transaction.goalId || null
  };
}

function mapGoalFromDb(item) {
  return {
    id: item.id,
    name: item.name,
    target: Number(item.target_amount),
    saved: Number(item.saved_amount || 0),
    icon: item.icon || "🎯",
    deadline: item.deadline,
    currencyCode: item.currency_code,
    savingsAccountId: item.savings_account_id || "",
    status: item.status,
    completedAt: item.completed_at
  };
}

function mapGoalToDb(userId, goal, forcedId) {
  return {
    ...(forcedId || goal.id ? { id: forcedId || goal.id } : {}),
    user_id: userId,
    name: goal.name,
    target_amount: goal.target,
    saved_amount: goal.saved || 0,
    icon: goal.icon || "🎯",
    deadline: goal.deadline || null,
    currency_code: goal.currencyCode,
    savings_account_id: goal.savingsAccountId || null,
    status: goal.status || "active",
    completed_at: goal.completedAt || null
  };
}

function mapLiabilityFromDb(item) {
  return {
    id: item.id,
    counterpartyName: item.counterparty_name,
    amount: Number(item.amount),
    currencyCode: item.currency_code,
    type: item.liability_type,
    dueDate: item.due_date || "",
    comment: item.comment || "",
    status: item.status || "open",
    settlementAccountId: item.settlement_account_id || "",
    settlementTransactionId: item.settlement_transaction_id || "",
    settledAt: item.settled_at || "",
    createdAt: item.created_at
  };
}

function mapLiabilityToDb(userId, liability, forcedId) {
  return {
    ...(forcedId || liability.id ? { id: forcedId || liability.id } : {}),
    user_id: userId,
    counterparty_name: liability.counterpartyName,
    amount: liability.amount,
    currency_code: liability.currencyCode,
    liability_type: liability.type,
    due_date: liability.dueDate || null,
    comment: liability.comment || null,
    status: liability.status || "open",
    settlement_account_id: liability.settlementAccountId || null,
    settlement_transaction_id: liability.settlementTransactionId || null,
    settled_at: liability.settledAt || null
  };
}

function throwIfError(error) {
  if (error) {
    throw error;
  }
}
