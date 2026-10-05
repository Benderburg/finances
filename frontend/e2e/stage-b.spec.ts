import { test, expect, type Page } from "./fixtures";

async function login(page: Page) {
  await page.goto("/login");
  await page.getByLabel("Email", { exact: true }).fill("dev@norocel.test");
  await page.getByLabel("Parolă", { exact: true }).fill("local-testing-123");
  await page
    .getByRole("button", { name: "Autentificare", exact: true })
    .click();
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(4);
}

async function post(page: Page, path: string, data: unknown) {
  const cookies = await page.context().cookies();
  return page.request.post("/api/v1" + path, {
    data,
    headers: {
      "X-XSRF-TOKEN": decodeURIComponent(
        cookies.find((c) => c.name === "XSRF-TOKEN")?.value ?? "",
      ),
      "Idempotency-Key": crypto.randomUUID(),
      Accept: "application/json",
    },
  });
}

test("RON incomplete valuation and additive CSV with explicit duplicate confirmation", async ({
  page,
}, info) => {
  const errors: string[] = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await login(page);
  const name = `CSV ${info.project.name} ${Date.now()}`;
  const created = await post(page, "/accounts", {
    name,
    kind: "regular",
    currency_code: "RON",
    opening_balance_minor: "10000",
  });
  expect(created.ok()).toBeTruthy();
  const account = (await created.json()).data;
  await page.goto("/app");
  await page.getByText("Cursuri și evaluare", { exact: true }).click();
  await page.getByLabel("Data evaluării", { exact: true }).fill("2020-01-01");
  await expect(page.locator(".valuation-settings")).toContainText(
    "Calcul incomplet",
  );
  await expect(page.locator(".valuation-settings")).toContainText("100,00 RON");
  await page
    .getByLabel("Moneda de afișare", { exact: true })
    .selectOption("RON");
  await expect(page.locator(".hero-balance .currency-tag")).toContainText(
    "RON",
  );
  await page.goto("/csv");
  const csv = `date;amount;description\r\n2026-10-01;-1.00;"Coffee; ""cup""\nline ${name}"\r\n2026-10-01;-1.00;"Coffee; ""cup""\nline ${name}"\r\n`;
  await page.getByLabel("Fișier CSV", { exact: true }).setInputFiles({
    name: "bank.csv",
    mimeType: "text/csv",
    buffer: Buffer.from(csv, "utf8"),
  });
  await page
    .getByRole("button", { name: "Detectează formatul", exact: true })
    .click();
  await expect(page.getByLabel("Separator", { exact: true })).toHaveValue(";");
  await page.getByLabel("Cont", { exact: true }).selectOption(account.id);
  const categories = (
    await (await page.request.get("/api/v1/categories?per_page=100")).json()
  ).data.items;
  await page
    .getByLabel("Venit · Categorie", { exact: true })
    .selectOption(
      categories.find(
        (c: { system_code: string }) => c.system_code === "salary",
      ).id,
    );
  await page
    .getByLabel("Cheltuială · Categorie", { exact: true })
    .selectOption(
      categories.find((c: { system_code: string }) => c.system_code === "food")
        .id,
    );
  await page
    .getByLabel(
      "Confirm codificarea, separatorul și coloanele; am verificat transferurile.",
    )
    .check();
  await page
    .getByRole("button", { name: "Verifică rândurile", exact: true })
    .click();
  await expect(
    page.getByText("Posibil duplicat", { exact: true }),
  ).toBeVisible();
  await expect(page.getByLabel("Include 1", { exact: true })).toBeChecked();
  await expect(page.getByLabel("Include 2", { exact: true })).not.toBeChecked();
  await page.getByLabel("Include 2", { exact: true }).check();
  await page
    .getByLabel(
      "Confirm că rândurile suspecte selectate sunt operațiuni distincte",
    )
    .check();
  await page
    .getByRole("button", {
      name: "Adaugă rândurile selectate · 2",
      exact: true,
    })
    .click();
  await expect(
    page.getByText("Operațiuni adăugate: 2", { exact: true }),
  ).toBeVisible();
  const after = (
    await (await page.request.get("/api/v1/accounts/" + account.id)).json()
  ).data;
  expect(after.balance_minor).toBe("9800");
  const exported = await page.request.get("/api/v1/operations/export.csv");
  expect(exported.headers()["content-type"]).toContain("text/csv");
  const text = await exported.text();
  expect(text).toContain("#norocel-csv,1,excel-safe");
  expect(text).toContain("'Coffee;");
  await page.getByLabel("Fișier CSV", { exact: true }).setInputFiles({
    name: "repeat.csv",
    mimeType: "text/csv",
    buffer: Buffer.from(text, "utf8"),
  });
  await page
    .getByRole("button", { name: "Detectează formatul", exact: true })
    .click();
  await page
    .getByLabel(
      "Confirm codificarea, separatorul și coloanele; am verificat transferurile.",
    )
    .check();
  await page
    .getByRole("button", { name: "Verifică rândurile", exact: true })
    .click();
  await expect(
    page
      .getByText("Operațiune deja importată / ID duplicat", { exact: true })
      .first(),
  ).toBeVisible();
  await expect(
    page.getByRole("button", {
      name: "Adaugă rândurile selectate · 0",
      exact: true,
    }),
  ).toBeDisabled();
  await page.screenshot({
    path: `test-results/stage-b-${info.project.name}.png`,
    fullPage: true,
  });
  expect(errors).toEqual([]);
});
