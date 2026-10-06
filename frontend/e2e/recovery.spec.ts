import { readFileSync, writeFileSync } from "node:fs";
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
test("lost command response retries the same key once and expired session closes workspace", async ({
  page,
  context,
}, info) => {
  test.setTimeout(90000);
  await login(page);
  await page.goto("/accounts");
  await page.getByRole("button", { name: "Adaugă", exact: true }).click();
  const dialog = page.getByRole("dialog");
  const name = "Retry " + info.project.name + "-" + Date.now();
  await dialog.getByLabel("Denumire", { exact: true }).fill(name);
  await dialog.getByLabel("Sold inițial").fill("100");
  const keys: string[] = [];
  page.on("request", (r) => {
    if (r.url().endsWith("/api/v1/accounts") && r.method() === "POST")
      keys.push(r.headers()["idempotency-key"]);
  });
  await page.route("**/api/v1/accounts", async (route) => {
    if (route.request().method() === "POST") {
      const response = await route.fetch();
      expect(response.status()).toBe(200);
      await context.setOffline(true);
      await route.abort("connectionclosed");
    } else await route.continue();
  });
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(
    page
      .locator(".offline-screen")
      .getByRole("heading", { name: "Fără conexiune", exact: true }),
  ).toBeVisible();
  await context.setOffline(false);
  await expect(dialog.getByRole("alert")).toContainText("Rezultat necunoscut");
  await expect(dialog.getByLabel("Denumire", { exact: true })).toBeDisabled();
  await expect(
    dialog.getByRole("button", { name: "Anulează", exact: true }),
  ).toBeDisabled();
  await page.unroute("**/api/v1/accounts");
  await dialog.getByRole("button", { name: "Reîncearcă", exact: true }).click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await expect(page.getByRole("heading", { name, exact: true })).toBeVisible();
  expect(keys).toHaveLength(2);
  expect(keys[0]).toBe(keys[1]);
  const result = await (
    await page.request.get("/api/v1/accounts?per_page=100")
  ).json();
  expect(
    result.data.items.filter((a: { name: string }) => a.name === name),
  ).toHaveLength(1);
  await context.clearCookies();
  await page.goto("/reports");
  await expect(
    page.getByRole("button", { name: "Autentificare", exact: true }),
  ).toBeVisible();
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(0);
  expect(
    await page.evaluate(() => localStorage.getItem("norocel-summary-owner")),
  ).toBeNull();
});
test("PWA update waits for confirmation and then activates", async ({
  page,
}) => {
  test.setTimeout(90000);
  await login(page);
  await expect
    .poll(() =>
      page.evaluate(() => Boolean(navigator.serviceWorker.controller)),
    )
    .toBe(true);
  const path = "../backend/public/sw.js",
    source = readFileSync(path, "utf8");
  try {
    writeFileSync(path, source + "\n// Local update QA " + Date.now() + "\n");
    await page.evaluate(async () => {
      const r = await navigator.serviceWorker.getRegistration();
      await r?.update();
    });
    await expect(
      page.getByText("O versiune nouă este disponibilă"),
    ).toBeVisible();
    expect(
      await page.evaluate(async () =>
        Boolean((await navigator.serviceWorker.getRegistration())?.waiting),
      ),
    ).toBe(true);
    await page
      .getByRole("button", {
        name: "Repornește când ai terminat formularul",
        exact: true,
      })
      .click();
    await expect(
      page.locator(".currency-balance, .offline-screen .balance-card"),
    ).toHaveCount(4);
    await expect(
      page.getByText("O versiune nouă este disponibilă"),
    ).toHaveCount(0);
  } finally {
    writeFileSync(path, source);
  }
});
