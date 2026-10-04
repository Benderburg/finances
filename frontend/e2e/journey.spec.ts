import { test, expect, type Page } from "./fixtures";

async function login(page: Page) {
  await page.goto("/login");
  await page.getByLabel("Email", { exact: true }).fill("dev@norocel.test");
  await page.getByLabel("Parolă", { exact: true }).fill("local-testing-123");
  await page
    .getByRole("button", { name: "Autentificare", exact: true })
    .click();
  await expect(
    page.getByRole("heading", { name: "Banii tăi, într-un loc" }),
  ).toBeVisible();
}
async function goto(page: Page, path: string) {
  await page.goto(path);
  await expect(page.locator("h1").first()).toBeVisible();
}
test("real accounts, cash flow, transfers, goals, liability, backup and logout", async ({
  page,
}, testInfo) => {
  test.setTimeout(180000);
  const errors: string[] = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await login(page);
  const month = await page.getByLabel("Lună", { exact: true }).inputValue();
  const [year, number] = month.split("-").map(Number);
  const nextMonth = new Date(Date.UTC(year, number, 1))
    .toISOString()
    .slice(0, 7);
  const stamp = testInfo.project.name + "-" + Date.now();
  await goto(page, "/accounts");
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  let dialog = page.getByRole("dialog");
  await dialog.getByLabel("Denumire", { exact: true }).fill("Cash " + stamp);
  await dialog.getByLabel("Sold inițial").fill("1000");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Cash " + stamp }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog.getByLabel("Denumire", { exact: true }).fill("Second " + stamp);
  await dialog.getByLabel("Sold inițial").fill("0");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Second " + stamp }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog.getByLabel("Denumire", { exact: true }).fill("USD " + stamp);
  await dialog.getByLabel("Valută", { exact: true }).selectOption("USD");
  await dialog.getByLabel("Sold inițial").fill("0");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "USD " + stamp }),
  ).toBeVisible();
  await goto(page, "/operations");
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog
    .getByLabel("Cont", { exact: true })
    .selectOption({ label: "Cash " + stamp + " · MDL" });
  await dialog.getByLabel("Sumă · MDL").fill("10.25");
  await dialog
    .getByLabel("Categorie", { exact: true })
    .selectOption({ label: "Alimentație" });
  await dialog
    .getByLabel("Descriere")
    .fill("Expense " + stamp + " <script>alert(1)</script>");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(
    page.getByText("Expense " + stamp + " <script>alert(1)</script>", {
      exact: true,
    }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog.getByRole("button", { name: "Transfer", exact: true }).click();
  await dialog
    .getByLabel("Din cont")
    .selectOption({ label: "Cash " + stamp + " · MDL" });
  await dialog
    .getByLabel("În cont")
    .selectOption({ label: "Second " + stamp + " · MDL" });
  await dialog.getByLabel("Sumă · MDL").fill("100");
  await dialog.getByLabel("Descriere").fill("Transfer " + stamp);
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(
    page.getByText("Cash " + stamp + " → Second " + stamp, { exact: true }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog.getByRole("button", { name: "Transfer", exact: true }).click();
  await dialog
    .getByLabel("Din cont")
    .selectOption({ label: "Cash " + stamp + " · MDL" });
  await dialog
    .getByLabel("În cont")
    .selectOption({ label: "USD " + stamp + " · USD" });
  await dialog.getByLabel("Sumă · MDL").fill("100");
  await dialog
    .getByRole("button", { name: "Sumă retrasă + curs", exact: true })
    .click();
  await dialog
    .getByLabel("Curs: unități sursă pentru 1 unitate destinație · MDL/USD")
    .fill("3");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await page
    .getByText("Cash " + stamp + " → USD " + stamp, { exact: true })
    .click();
  dialog = page.getByRole("dialog");
  await expect(dialog).toContainText("33,33 USD");
  await dialog.getByRole("button", { name: "Editează", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog.getByLabel("Sumă · MDL").fill("90");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await page
    .getByText("Cash " + stamp + " → USD " + stamp, { exact: true })
    .click();
  dialog = page.getByRole("dialog");
  await expect(dialog).toContainText("30,00 USD");
  await dialog.getByText("Istoricul modificărilor").click();
  await expect(dialog).toContainText("Modificat");
  page.once("dialog", (d) => d.accept());
  await dialog
    .getByRole("button", { name: "Anulează operațiunea", exact: true })
    .click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await goto(page, "/goals");
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog.getByLabel("Denumire", { exact: true }).fill("Trip " + stamp);
  await dialog.getByLabel("Sumă țintă").fill("100");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  const goal = page
    .locator(".entity-card")
    .filter({ has: page.getByRole("heading", { name: "Trip " + stamp }) });
  await expect(goal).toBeVisible();
  await goal.getByRole("button", { name: "Alimentează", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog
    .getByLabel("Din cont")
    .selectOption({ label: "Cash " + stamp + " · MDL" });
  await dialog.getByLabel("Sumă · MDL").fill("100");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await goal
    .getByRole("button", { name: "Cheltuie pentru obiectiv", exact: true })
    .click();
  dialog = page.getByRole("dialog");
  await dialog.getByLabel("Sumă · MDL").fill("40");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(goal).toContainText("40,00 MDL");
  await expect(goal).toContainText("Sumă colectată");
  await goal
    .getByRole("button", { name: "Cheltuie pentru obiectiv", exact: true })
    .click();
  dialog = page.getByRole("dialog");
  await dialog.getByLabel("Sumă · MDL").fill("10");
  await dialog.getByLabel("Descriere").fill("Completion " + stamp);
  await dialog.getByLabel("Finalizează obiectivul").check();
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(goal).toContainText("Finalizat");
  await goto(page, "/operations");
  await page.getByText("Completion " + stamp, { exact: true }).click();
  dialog = page.getByRole("dialog");
  page.once("dialog", (d) => d.accept());
  await dialog
    .getByRole("button", { name: "Anulează operațiunea", exact: true })
    .click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await goto(page, "/goals");
  await expect(goal).toContainText("Sumă colectată");
  await goto(page, "/liabilities");
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog.getByLabel("Persoană / organizație").fill("Friend " + stamp);
  await dialog.getByLabel("Sumă", { exact: true }).fill("20");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  const liability = page
    .locator(".entity-card")
    .filter({ has: page.getByRole("heading", { name: "Friend " + stamp }) });
  await liability
    .getByRole("button", { name: "Rambursează integral", exact: true })
    .click();
  dialog = page.getByRole("dialog");
  await dialog
    .getByLabel("Cont", { exact: true })
    .selectOption({ label: "Cash " + stamp + " · MDL" });
  await dialog
    .getByLabel("Categorie", { exact: true })
    .selectOption({ label: "Alte cheltuieli" });
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(liability).toContainText("Rambursat");
  page.once("dialog", (d) => d.accept());
  await liability
    .getByRole("button", { name: "Anulează rambursarea", exact: true })
    .click();
  await expect(liability).toContainText("Deschis");
  await goto(page, "/operations");
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog
    .getByLabel("Cont", { exact: true })
    .selectOption({ label: "Cash " + stamp + " · MDL" });
  await dialog.getByLabel("Sumă · MDL").fill("101");
  await dialog
    .getByRole("button", { name: "Adaugă Categorie", exact: true })
    .click();
  let nested = page.getByRole("dialog").last();
  await nested.getByLabel("Denumire", { exact: true }).fill("Limit " + stamp);
  await nested.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(page.getByRole("dialog")).toHaveCount(1);
  dialog = page.getByRole("dialog");
  await expect(dialog.getByLabel("Categorie", { exact: true })).not.toHaveValue(
    "",
  );
  await dialog.getByLabel("Descriere").fill("Budget expense " + stamp);
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(
    page.getByText("Budget expense " + stamp, { exact: true }),
  ).toBeVisible();
  await goto(page, "/budgets");
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog
    .getByLabel("Categorie", { exact: true })
    .selectOption({ label: "Limit " + stamp });
  await dialog.getByLabel("Limită", { exact: true }).fill("50");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  let budget = page
    .locator(".entity-card")
    .filter({ has: page.getByRole("heading", { name: "Limit " + stamp }) });
  await expect(budget).toContainText("Limită depășită");
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await page.getByLabel("Lună", { exact: true }).fill(nextMonth);
  await expect(budget).toHaveCount(0);
  await page.getByLabel("Lună", { exact: true }).fill(month);
  await expect(budget).toBeVisible();
  await goto(page, "/settings");
  const [download] = await Promise.all([
    page.waitForEvent("download"),
    page.getByRole("button", { name: "Exportă JSON", exact: true }).click(),
  ]);
  const file = await download.path();
  await page
    .getByLabel("Restabilește JSON", { exact: true })
    .setInputFiles(file!);
  await page
    .getByRole("button", { name: "Verifică fișierul", exact: true })
    .click();
  await expect(page.locator(".restore-preview")).toBeVisible();
  page.once("dialog", (d) => d.accept());
  await page
    .getByRole("button", {
      name: "Înlocuiește datele cu copia verificată",
      exact: true,
    })
    .click();
  await expect(
    page.getByRole("button", {
      name: "Descarcă copia de dinaintea restaurării",
    }),
  ).toBeVisible();
  await page.getByLabel("Restabilește JSON", { exact: true }).setInputFiles({
    name: "invalid.json",
    mimeType: "application/json",
    buffer: Buffer.from("{broken"),
  });
  await page
    .getByRole("button", { name: "Verifică fișierul", exact: true })
    .click();
  await expect(page.getByRole("alert")).toContainText("Fișier JSON invalid");
  await goto(page, "/reports");
  await expect(
    page.getByRole("heading", { name: "Flux de numerar · MDL" }),
  ).toBeVisible();
  await expect(page.locator(".balance-chart").first()).toBeVisible();
  await page.emulateMedia({ media: "print" });
  await page.screenshot({
    path: `test-results/reports-${testInfo.project.name}.png`,
    fullPage: true,
  });
  await page.emulateMedia({ media: "screen" });
  await goto(page, "/");
  await expect(page.locator(".balance-card")).toHaveCount(4);
  await expect(
    page.locator(".operation-row").filter({ hasText: "…" }),
  ).toHaveCount(0);
  await page.screenshot({
    path: `test-results/dashboard-${testInfo.project.name}.png`,
    fullPage: true,
  });
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  expect(errors).toEqual([]);
  await goto(page, "/settings");
  await page
    .getByRole("main")
    .getByRole("button", { name: "Ieșire", exact: true })
    .click();
  await expect(
    page.getByRole("button", { name: "Autentificare", exact: true }),
  ).toBeVisible();
  await page.goBack();
  await expect(
    page.getByRole("heading", { name: "Banii tăi, într-un loc" }),
  ).toHaveCount(0);
});
