import { normalizeCategory } from "./config.js";

export async function signIn(supabase, email, password) {
  const { error } = await supabase.auth.signInWithPassword({ email, password });
  if (error) {
    throw error;
  }
}

export async function signUp(supabase, email, password, fullName) {
  const { error } = await supabase.auth.signUp({
    email,
    password,
    options: {
      data: {
        full_name: fullName || null
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

export async function saveProfile(supabase, user) {
  const payload = {
    id: user.id,
    email: user.email,
    full_name: user.user_metadata?.full_name || user.email?.split("@")[0] || "User"
  };

  const { error } = await supabase.from("profiles").upsert(payload);
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

export async function loadUserWorkspace(supabase, userId) {
  const [profileResult, transactionsResult, budgetsResult, goalsResult] = await Promise.all([
    supabase.from("profiles").select("*").eq("id", userId).maybeSingle(),
    supabase.from("transactions").select("*").eq("user_id", userId).order("transaction_date", { ascending: false }),
    supabase.from("budgets").select("*").eq("user_id", userId).order("category", { ascending: true }),
    supabase.from("goals").select("*").eq("user_id", userId).order("created_at", { ascending: false })
  ]);

  throwIfError(profileResult.error);
  throwIfError(transactionsResult.error);
  throwIfError(budgetsResult.error);
  throwIfError(goalsResult.error);

  return {
    profile: profileResult.data,
    transactions: (transactionsResult.data || []).map(mapTransactionFromDb),
    budgets: (budgetsResult.data || []).reduce((accumulator, item) => {
      accumulator[normalizeCategory(item.category)] = Number(item.monthly_limit);
      return accumulator;
    }, {}),
    goals: (goalsResult.data || []).map(mapGoalFromDb)
  };
}

export async function createTransaction(supabase, userId, transaction) {
  const { error } = await supabase.from("transactions").insert(mapTransactionToDb(userId, transaction));
  if (error) {
    throw error;
  }
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

export async function saveBudget(supabase, userId, category, monthlyLimit) {
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

export async function saveGoal(supabase, userId, goal) {
  if (goal.id) {
    const { error } = await supabase
      .from("goals")
      .update(mapGoalToDb(userId, goal))
      .eq("id", goal.id)
      .eq("user_id", userId);

    if (error) {
      throw error;
    }

    return;
  }

  const { error } = await supabase.from("goals").insert(mapGoalToDb(userId, goal));
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

export async function exportUserData(supabase, userId) {
  const workspace = await loadUserWorkspace(supabase, userId);
  return {
    profile: workspace.profile,
    transactions: workspace.transactions,
    budgets: workspace.budgets,
    goals: workspace.goals
  };
}

export async function importUserData(supabase, userId, payload) {
  const transactions = Array.isArray(payload.transactions) ? payload.transactions : [];
  const goals = Array.isArray(payload.goals) ? payload.goals : [];
  const budgets = payload.budgets && typeof payload.budgets === "object" ? payload.budgets : {};
  const profile = payload.profile && typeof payload.profile === "object" ? payload.profile : null;

  await Promise.all([
    supabase.from("transactions").delete().eq("user_id", userId),
    supabase.from("goals").delete().eq("user_id", userId),
    supabase.from("budgets").delete().eq("user_id", userId)
  ]);

  if (transactions.length) {
    const { error } = await supabase.from("transactions").insert(
      transactions.map((item) => mapTransactionToDb(userId, item, item.id))
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

function mapTransactionFromDb(item) {
  return {
    id: item.id,
    amount: Number(item.amount),
    desc: item.description || "",
    date: item.transaction_date,
    category: normalizeCategory(item.category),
    type: item.type
  };
}

function mapTransactionToDb(userId, transaction, forcedId) {
  return {
    ...(forcedId || transaction.id ? { id: forcedId || transaction.id } : {}),
    user_id: userId,
    amount: transaction.amount,
    description: transaction.desc || null,
    transaction_date: transaction.date,
    category: normalizeCategory(transaction.category),
    type: transaction.type
  };
}

function mapGoalFromDb(item) {
  return {
    id: item.id,
    name: item.name,
    target: Number(item.target_amount),
    saved: Number(item.saved_amount),
    icon: item.icon || "🎯",
    deadline: item.deadline
  };
}

function mapGoalToDb(userId, goal, forcedId) {
  return {
    ...(forcedId || goal.id ? { id: forcedId || goal.id } : {}),
    user_id: userId,
    name: goal.name,
    target_amount: goal.target,
    saved_amount: goal.saved,
    icon: goal.icon || "🎯",
    deadline: goal.deadline || null
  };
}

function throwIfError(error) {
  if (error) {
    throw error;
  }
}
