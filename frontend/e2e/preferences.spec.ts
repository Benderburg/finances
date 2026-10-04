import { test, expect, type Page } from "./fixtures";

async function settings(page: Page, body: Record<string, string>) {
  const status = await page.evaluate(async (body) => {
    const token = decodeURIComponent(
      document.cookie
        .split("; ")
        .find((c) => c.startsWith("XSRF-TOKEN="))
        ?.slice(11) ?? "",
    );
    const r = await fetch("/api/v1/me", {
      method: "PATCH",
      credentials: "include",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-XSRF-TOKEN": token,
        "Idempotency-Key": crypto.randomUUID(),
      },
      body: JSON.stringify(body),
    });
    return r.status;
  }, body);
  expect(status).toBe(200);
}
test("languages, themes, responsive layouts, offline snapshot and offline logout", async ({
  page,
  context,
}, info) => {
  test.setTimeout(120000);
  await page.goto("/login");
  await page.getByLabel("Email", { exact: true }).fill("dev@norocel.test");
  await page.getByLabel("Parolă", { exact: true }).fill("local-testing-123");
  await page
    .getByRole("button", { name: "Autentificare", exact: true })
    .click();
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(4);
  await page.goto("/settings");
  await page.getByLabel("Păstrează ultima sinteză pe acest dispozitiv").check();
  await page.goto("/");
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(4);
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          new Promise<number>((resolve, reject) => {
            const r = indexedDB.open("norocel-summary", 1);
            r.onsuccess = () => {
              const q = r.result
                .transaction("summaries")
                .objectStore("summaries")
                .count();
              q.onsuccess = () => {
                resolve(q.result);
                r.result.close();
              };
            };
            r.onerror = () => reject(r.error);
          }),
      ),
    )
    .toBe(1);
  await page.goto("/settings");
  await page
    .getByRole("main")
    .getByRole("button", { name: "Editează", exact: true })
    .click();
  let dialog = page.getByRole("dialog");
  await dialog.getByLabel("Limbă").selectOption("ru");
  await dialog.getByLabel("Temă").selectOption("dark");
  await dialog.getByRole("button", { name: "Salvează", exact: true }).click();
  await expect(page.locator("html")).toHaveAttribute("lang", "ru");
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  await page.goto("/reports");
  await expect(
    page.getByRole("heading", { name: "Отчёты", exact: true }),
  ).toBeVisible();
  await expect(page.locator(".balance-chart").first()).toBeVisible();
  await page.screenshot({
    path: `test-results/dark-ru-${info.project.name}.png`,
    fullPage: true,
  });
  await settings(page, { locale: "en", theme: "light" });
  await page.goto("/settings");
  await expect(
    page.getByRole("heading", { name: "Settings", exact: true }),
  ).toBeVisible();
  await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
  await settings(page, { locale: "ro", theme: "system" });
  await page.goto("/");
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(4);
  for (const width of [360, 390, 430, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true);
  }
  await expect
    .poll(() =>
      page.evaluate(() => Boolean(navigator.serviceWorker.controller)),
    )
    .toBe(true);
  const cached = await page.evaluate(async () => {
    const keys = await caches.keys();
    return (
      await Promise.all(
        keys.map(async (key) =>
          (await (await caches.open(key)).keys()).map(
            (r) => new URL(r.url).pathname,
          ),
        ),
      )
    ).flat();
  });
  expect(cached.length).toBeGreaterThan(0);
  expect(
    cached.some((p) => /^\/(api|auth|sanctum|admin|backup)(\/|$)/.test(p)),
  ).toBe(false);
  await context.setOffline(true);
  await page.reload();
  await expect(
    page.getByRole("heading", { name: "Norocel · Fără conexiune" }),
  ).toBeVisible();
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(4);
  await expect(
    page.getByRole("button", { name: "Salvează", exact: true }),
  ).toHaveCount(0);
  await page.getByRole("button", { name: "Ieșire", exact: true }).click();
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(0);
  await expect(
    page.getByText("Ai ieșit de pe dispozitiv.", { exact: false }),
  ).toBeVisible();
  expect(
    await page.evaluate(() => localStorage.getItem("norocel-summary-owner")),
  ).toBeNull();
  await context.setOffline(false);
  await expect(
    page.getByRole("button", { name: "Autentificare", exact: true }),
  ).toBeVisible();
  expect((await context.request.get("/api/v1/me")).status()).toBe(401);
});
