import { test, expect, type Page } from "@playwright/test";

// Isolated visual fixtures: these tests never write to a financial workspace.
test.use({ serviceWorkers: "block" });
const entity = {
  revision: 1,
  created_at: "2026-10-01",
  updated_at: "2026-10-01",
};
const account = (
  id: string,
  name: string,
  currency_code: string,
  balance_minor: string,
  kind = "regular",
) => ({
  ...entity,
  id,
  name,
  currency_code,
  balance_minor,
  kind,
  opening_balance_minor: balance_minor,
  include_in_total: true,
  archived_at: null,
  goal_id: kind === "savings" ? "goal" : null,
});
const accounts = [
  account("main", "Основной счёт", "MDL", "1245000"),
  account("cash", "Наличные", "MDL", "230000"),
  account("euro", "Отпуск", "EUR", "62000", "savings"),
  account("usd", "Резерв", "USD", "150000", "savings"),
];
const categories = [
  {
    ...entity,
    id: "food",
    name: null,
    system_code: "food",
    kind: "expense",
    archived_at: null,
    is_system: true,
  },
  {
    ...entity,
    id: "salary",
    name: null,
    system_code: "salary",
    kind: "income",
    archived_at: null,
    is_system: true,
  },
];
const operation = (
  id: string,
  type: string,
  amount_minor: string,
  occurred_on: string,
) => ({
  ...entity,
  id,
  type,
  status: "posted",
  amount_minor,
  occurred_on,
  currency_code: "MDL",
  description: type === "expense" ? "Продукты на неделю" : "Зарплата",
  account_id: "main",
  from_account_id: null,
  to_account_id: null,
  category_id: type === "expense" ? "food" : "salary",
  goal_id: null,
  liability_id: null,
  goal_completion_requested: false,
  target_amount_minor: null,
  target_currency_code: null,
  quoted_rate: null,
  effective_rate: null,
});
const operations = [
  operation("expense", "expense", "35000", "2026-10-04"),
  operation("income", "income", "800000", "2026-10-03"),
  {
    ...operation("transfer", "transfer", "100000", "2026-10-03"),
    description: "",
    account_id: null,
    category_id: null,
    from_account_id: "main",
    to_account_id: "cash",
    target_amount_minor: "100000",
    target_currency_code: "MDL",
  },
];
const goal = {
  ...entity,
  id: "goal",
  name: "Отпуск в Италии",
  icon: null,
  currency_code: "EUR",
  target_amount_minor: "100000",
  savings_account_id: "euro",
  deadline: "2027-06-01",
  status: "active",
  saved_now_minor: "62000",
  spent_on_goal_minor: "0",
  funded_lifetime_minor: "62000",
  progress: "62",
  legacy_read_only: false,
  completed_at: null,
};
const valuation = (known_subtotal: Record<string, string>) => ({
  currency_code: "MDL",
  known_subtotal,
  incomplete: false,
  missing: [],
  unconverted: [],
  meta: { rates: [], cache_key: "fixture" },
});
const flow = {
  income_minor: "800000",
  expense_minor: "35000",
  net_minor: "765000",
};
const balances = {
  MDL: {
    total_minor: "1475000",
    available_minor: "1475000",
    savings_minor: "0",
  },
  EUR: { total_minor: "62000", available_minor: "0", savings_minor: "62000" },
  USD: { total_minor: "150000", available_minor: "0", savings_minor: "150000" },
  RON: { total_minor: "0", available_minor: "0", savings_minor: "0" },
};
const liabilities = [
  {
    ...entity,
    id: "debt",
    kind: "receivable",
    counterparty_name: "Алексей",
    principal_minor: "120000",
    currency_code: "MDL",
    due_on: "2026-10-20",
    comment: "",
    status: "open",
    settlement_operation: null,
  },
];
const budget = {
  ...entity,
  id: "budget",
  category_id: "food",
  period_month: "2026-10-01",
  currency_code: "MDL",
  limit_minor: "300000",
  fact_minor: "35000",
  remaining_minor: "265000",
  progress: "11.67",
  disabled: false,
  other_currencies: {},
  valuation: valuation({ amount_minor: "35000" }),
};

async function fixture(page: Page, theme: string, empty = false) {
  const writes: { path: string; body: unknown }[] = [];
  await page.route("**/sanctum/csrf-cookie", (route) =>
    route.fulfill({ status: 204 }),
  );
  await page.route("**/api/v1/**", async (route) => {
    const url = new URL(route.request().url()),
      path = url.pathname.replace("/api/v1", "");
    const list = (items: unknown[]) => ({
      items: empty ? [] : items,
      pagination: { page: 1, pages: 1, total: empty ? 0 : items.length },
    });
    let data: unknown = list([]);
    if (route.request().method() !== "GET") {
      writes.push({ path, body: route.request().postDataJSON() });
      data = {};
    } else if (path === "/me")
      data = {
        user: {
          id: "visual",
          full_name: "Александр",
          email: "visual@example.test",
          email_verified_at: "2026-10-01",
          is_admin: false,
          billing_plan: "regular",
          avatar_url: null,
        },
        settings: {
          locale: "ru",
          theme,
          base_currency_code: "MDL",
          timezone: "Europe/Chisinau",
          workspace_revision: 1,
          workspace_generation: 1,
        },
        pending_email: null,
      };
    else if (path === "/accounts")
      data = list(
        accounts.filter(
          (a) =>
            !url.searchParams.has("kind") ||
            a.kind === url.searchParams.get("kind"),
        ),
      );
    else if (path === "/categories") data = list(categories);
    else if (path === "/goals") data = list([goal]);
    else if (path === "/liabilities")
      data = list(
        liabilities.filter(
          (l) =>
            !url.searchParams.has("kind") ||
            l.kind === url.searchParams.get("kind"),
        ),
      );
    else if (path === "/budgets") data = list([budget]);
    else if (path === "/operations") data = list(operations);
    else if (path.startsWith("/operations/"))
      data = { ...operations.find((o) => path.endsWith(o.id)), history: [] };
    else if (path === "/dashboard")
      data = {
        month: "2026-10",
        valuation_date: "2026-10-04",
        consolidated: {
          balances: valuation({
            total_minor: "5373700",
            available_minor: "1475000",
            savings_minor: "3898700",
          }),
          cash_flow: valuation(flow),
        },
        balances,
        cash_flow: Object.fromEntries(
          Object.keys(balances).map((c) => [
            c,
            c === "MDL"
              ? flow
              : { income_minor: "0", expense_minor: "0", net_minor: "0" },
          ]),
        ),
        recent_operations: empty ? [] : operations,
        goals: empty ? [] : [goal],
        budgets: empty ? [] : [budget],
        liabilities: empty ? [] : liabilities,
      };
    else if (path === "/fx/reference")
      data = { rates: [], fetch: { available: true } };
    else if (path === "/reports/cash-flow")
      data = {
        consolidated: valuation(flow),
        currencies: { MDL: flow },
        months: empty
          ? []
          : ["2026-08", "2026-09", "2026-10"].map((month) => ({
              month,
              consolidated: valuation(flow),
              currencies: { MDL: flow },
            })),
      };
    else if (path === "/reports/expenses")
      data = {
        consolidated: valuation(empty ? {} : { food: "35000" }),
        categories: [],
      };
    else if (path === "/reports/balances")
      data = {
        consolidated_points: [],
        reconstructed_history: true,
        accounts: empty
          ? []
          : [
              {
                account_id: "main",
                name: "Основной счёт",
                currency_code: "MDL",
                points: [
                  { date: "2026-10-01", balance_minor: "100000" },
                  { date: "2026-10-04", balance_minor: "1245000" },
                ],
              },
            ],
      };
    await route.fulfill({ json: { data, meta: { workspace_revision: 1 } } });
  });
  return writes;
}

for (const theme of ["light", "dark"])
  for (const width of [360, 390, 430, 768, 1024, 1440]) {
    test(`redesign ${theme} ${width}: routes fit and amounts stay visible`, async ({
      page,
    }, info) => {
      const errors: string[] = [];
      page.on("pageerror", (error) => errors.push(error.message));
      await fixture(page, theme);
      await page.setViewportSize({ width, height: 900 });
      for (const path of [
        "/app",
        "/accounts",
        "/operations",
        "/savings",
        "/goals",
        "/liabilities",
        "/budgets",
        "/reports",
        "/settings",
        "/categories",
        "/csv",
        "/more",
      ]) {
        await page.goto(path);
        await expect(page.locator("main h1")).toBeVisible();
        await expect(page.locator(".loading-state")).toHaveCount(0);
        await page.evaluate(() => document.fonts.ready);
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
          ),
          path,
        ).toBe(true);
        await expect(page.getByRole("alert")).toHaveCount(0);
        if (path === "/app") {
          await expect(page.locator(".hero-balance")).toContainText(
            "14 750,00 MDL",
          );
          await expect(page.locator(".account-preview")).toHaveCount(4);
          if ([390, 1440].includes(width))
            await page.screenshot({
              path: info.outputPath(`dashboard-${theme}-${width}.png`),
              fullPage: true,
            });
        }
      }
      expect(errors).toEqual([]);
    });
  }

test("quick actions, savings transfer, exchange and custom confirmations", async ({
  page,
}) => {
  const writes = await fixture(page, "light");
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/app");
  await page.locator(".quick-add").click();
  let dialog = page.getByRole("dialog");
  await expect(dialog).toHaveAccessibleName("Что хотите записать?");
  await dialog.getByRole("button", { name: /Отложить/ }).click();
  await dialog.getByRole("button", { name: /Отпуск/ }).click();
  dialog = page.getByRole("dialog");
  await expect(dialog.getByLabel("На счёт", { exact: true })).toHaveValue(
    "euro",
  );
  await dialog.getByLabel("Со счёта", { exact: true }).selectOption("main");
  await expect(dialog.locator("input").first()).toHaveAttribute(
    "inputmode",
    "decimal",
  );
  await dialog.getByLabel("Сумма · MDL").fill("1000");
  await expect(
    dialog.getByRole("button", { name: "Оценка по BNM", exact: true }),
  ).toBeVisible();
  await dialog.getByRole("button", { name: "Закрыть", exact: true }).click();
  expect(writes).toHaveLength(0);
  await page.goto("/operations");
  await expect(page.locator(".transaction-day")).toHaveCount(2);
  await page.getByRole("button", { name: /Продукты на неделю/ }).click();
  await page
    .getByRole("dialog")
    .getByRole("button", { name: "Отменить операцию", exact: true })
    .click();
  const confirmation = page.getByRole("dialog", {
    name: "Подтвердите действие",
    exact: true,
  });
  await confirmation
    .getByRole("button", { name: "Отмена", exact: true })
    .click();
  expect(writes).toHaveLength(0);
  await page
    .getByRole("dialog")
    .getByRole("button", { name: "Отменить операцию", exact: true })
    .click();
  await confirmation
    .getByRole("button", { name: "Подтвердить", exact: true })
    .click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  expect(writes).toEqual([
    { path: "/operations/expense/void", body: { expected_revision: 1 } },
  ]);
});

test("empty screens offer working next steps and network errors can retry", async ({
  page,
}) => {
  await fixture(page, "dark", true);
  await page.setViewportSize({ width: 360, height: 800 });
  for (const path of [
    "/accounts",
    "/operations",
    "/savings",
    "/goals",
    "/liabilities",
    "/budgets",
    "/reports",
  ]) {
    await page.goto(path);
    await expect(page.locator(".empty").first()).toBeVisible();
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
      path,
    ).toBe(true);
  }
  await page.goto("/savings?new=1");
  await expect(
    page.getByRole("dialog").getByLabel("Тип", { exact: true }),
  ).toHaveValue("savings");
  await page
    .getByRole("dialog")
    .getByRole("button", { name: "Закрыть", exact: true })
    .click();
  let fails = true;
  await page.route("**/api/v1/operations?*", (route) =>
    fails
      ? route.fulfill({
          status: 503,
          json: { error: { code: "REQUEST_FAILED" } },
        })
      : route.fulfill({
          json: {
            data: { items: [], pagination: { page: 1, pages: 1, total: 0 } },
            meta: { workspace_revision: 1 },
          },
        }),
  );
  await page.goto("/operations");
  await expect(page.getByRole("alert")).toBeVisible();
  fails = false;
  await page.getByRole("button", { name: "Повторить", exact: true }).click();
  await expect(page.getByRole("alert")).toHaveCount(0);
  await expect(page.locator(".empty")).toBeVisible();
});
