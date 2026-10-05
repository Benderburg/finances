import { readFileSync } from "node:fs";
import { test, expect, type Page } from "./fixtures";

const mailbox = () =>
  readFileSync("../backend/storage/logs/laravel.log", "utf8");
const mailOrigin = (
  process.env.NOROCEL_QA_URL ?? "http://127.0.0.1:8000"
).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
async function login(page: Page, email: string, password: string) {
  await page.goto("/login");
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page.getByLabel("Parolă", { exact: true }).fill(password);
  await page
    .getByRole("button", { name: "Autentificare", exact: true })
    .click();
}
test("register, email verification, income, expense, password reset and user isolation", async ({
  page,
}, info) => {
  test.setTimeout(120000);
  const stamp = info.project.name + "-" + Date.now(),
    email = "e2e-" + stamp + "@example.test",
    password = "registration-test-123";
  await page.goto("/register");
  await page.getByLabel("Nume complet").fill("Member " + stamp);
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page.getByLabel("Parolă", { exact: true }).fill(password);
  await page.getByLabel("Confirmă parola").fill(password);
  await page.getByRole("button", { name: "Creează cont", exact: true }).click();
  await expect(
    page.getByRole("heading", {
      name: "Confirmă adresa de email pentru a continua",
    }),
  ).toBeVisible();
  const me = await (await page.request.get("/api/v1/me")).json();
  const pattern = new RegExp(
    mailOrigin + "/auth/verify-email/" + me.data.user.id + "/[^\\s<>()]+",
  );
  await expect.poll(() => mailbox().match(pattern)?.[0]).toBeTruthy();
  const confirmationContext = await page.context().browser()!.newContext();
  try {
    const confirmationPage = await confirmationContext.newPage();
    await confirmationPage.goto(
      mailbox().match(pattern)![0].replaceAll("&amp;", "&"),
    );
    await expect(confirmationPage.locator(".currency-balance")).toHaveCount(4);
    expect(
      (
        await confirmationPage.request.get(
          new URL("/api/v1/me", confirmationPage.url()).href,
        )
      ).status(),
    ).toBe(200);
  } finally {
    await confirmationContext.close();
  }
  await page
    .getByRole("button", { name: "Am confirmat emailul", exact: true })
    .click();
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(4);
  await page.goto("/accounts");
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  let dialog = page.getByRole("dialog");
  await dialog.getByLabel("Denumire", { exact: true }).fill("Private " + stamp);
  await dialog.getByLabel("Sold inițial").fill("0");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Private " + stamp }),
  ).toBeVisible();
  await page.goto("/operations");
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog.getByRole("button", { name: "Venit", exact: true }).click();
  await dialog
    .getByLabel("Cont", { exact: true })
    .selectOption({ label: "Private " + stamp + " · MDL" });
  await dialog.getByLabel("Sumă · MDL").fill("500");
  await dialog
    .getByLabel("Categorie", { exact: true })
    .selectOption({ label: "Salariu" });
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  dialog = page.getByRole("dialog");
  await dialog
    .getByLabel("Cont", { exact: true })
    .selectOption({ label: "Private " + stamp + " · MDL" });
  await dialog.getByLabel("Sumă · MDL").fill("200");
  await dialog
    .getByLabel("Categorie", { exact: true })
    .selectOption({ label: "Alimentație" });
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await page.goto("/app");
  await expect(page.locator(".currency-balance:first-child")).toContainText(
    "300,00 MDL",
  );
  await page.goto("/settings");
  await page
    .getByRole("main")
    .getByRole("button", { name: "Ieșire", exact: true })
    .click();
  await expect(page).toHaveURL(/\/$/);
  await page.goto("/login");
  await page.goto("/forgot-password");
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page.getByRole("button", { name: "Trimite link", exact: true }).click();
  await expect(page.locator(".success")).toBeVisible();
  const resetPattern = new RegExp(
    mailOrigin + "/reset-password\\?[^\\s<>()]+",
    "g",
  );
  const link = mailbox().match(resetPattern)?.at(-1);
  expect(link).toBeTruthy();
  await page.goto(link!.replaceAll("&amp;", "&"));
  await page.getByLabel("Parolă", { exact: true }).fill("recovered-test-456");
  await page.getByLabel("Confirmă parola").fill("recovered-test-456");
  await page
    .getByRole("button", { name: "Setează parola nouă", exact: true })
    .click();
  await expect(page.locator(".success")).toBeVisible();
  await login(page, email, "recovered-test-456");
  await expect(page.locator(".currency-balance:first-child")).toContainText(
    "300,00 MDL",
  );
  await page.goto("/settings");
  await page
    .getByRole("main")
    .getByRole("button", { name: "Ieșire", exact: true })
    .click();
  await expect(page).toHaveURL(/\/$/);
  await page.goto("/login");
  await login(page, "dev@norocel.test", "local-testing-123");
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(4);
  await page.goto("/accounts");
  await expect(
    page.getByRole("heading", { name: "Private " + stamp }),
  ).toHaveCount(0);
  const foreign = me.data.user.id;
  expect(
    (await page.request.get("/api/v1/accounts?user_id=" + foreign)).status(),
  ).toBe(200);
});

test("registration mail failure offers resend with feedback and a cooldown", async ({
  page,
}, info) => {
  const email = `mail-retry-${info.project.name}-${Date.now()}@example.test`;
  await page.route("**/auth/register", async (route) => {
    const response = await route.fetch();
    expect(response.status()).toBe(201);
    await route.fulfill({
      response,
      json: { ...(await response.json()), verification_sent: false },
    });
  });
  let attempts = 0;
  await page.route("**/auth/resend-verification", async (route) => {
    attempts++;
    if (attempts === 1) {
      await route.fulfill({
        status: 503,
        json: { error: { code: "MAIL_DELIVERY_FAILED" } },
      });
    } else await route.continue();
  });
  await page.goto("/register");
  await page.getByLabel("Nume complet").fill("Mail retry");
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page
    .getByLabel("Parolă", { exact: true })
    .fill("registration-test-123");
  await page.getByLabel("Confirmă parola").fill("registration-test-123");
  await page.getByRole("button", { name: "Creează cont", exact: true }).click();
  await expect(
    page.getByRole("heading", {
      name: "Confirmă adresa de email pentru a continua",
    }),
  ).toBeVisible();
  await expect(
    page.getByText("Contul tău este păstrat.", { exact: false }),
  ).toBeVisible();
  await expect(page.getByText(email, { exact: true })).toBeVisible();
  await page
    .getByRole("button", { name: "Retrimite scrisoarea", exact: true })
    .click();
  await expect(
    page.getByText("Contul tău este păstrat.", { exact: false }),
  ).toBeVisible();
  await page
    .getByRole("button", { name: "Retrimite scrisoarea", exact: true })
    .click();
  await expect(page.getByRole("status")).toHaveText("Scrisoare trimisă");
  await expect(
    page.getByRole("button", { name: /Retrimite scrisoarea \(\d+\)/ }),
  ).toBeDisabled();
  expect((await page.request.get("/api/v1/accounts")).status()).toBe(403);
});
