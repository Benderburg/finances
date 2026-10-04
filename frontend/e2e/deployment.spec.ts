import { test, expect } from "./fixtures";

test("PHP-FPM Nginx deployment serves deep links, sessions, CSRF and built assets", async ({
  page,
  request,
}, info) => {
  test.skip(
    process.env.NOROCEL_DEPLOYMENT_QA !== "1",
    "Separate local Docker deployment smoke test",
  );
  test.setTimeout(120000);
  const base = process.env.NOROCEL_QA_URL ?? "http://127.0.0.1:8085";
  expect((await request.get(base + "/up")).status()).toBe(200);
  expect((await request.get(base + "/.env")).status()).toBe(403);
  expect(
    (await request.get(base + "/sw.js")).headers()["cache-control"],
  ).toContain("no-cache");
  await page.goto(base + "/operations");
  await page.getByLabel("Email", { exact: true }).fill("dev@norocel.test");
  await page.getByLabel("Parolă", { exact: true }).fill("local-testing-123");
  await page
    .getByRole("button", { name: "Autentificare", exact: true })
    .click();
  await expect(
    page.locator(".currency-balance, .offline-screen .balance-card"),
  ).toHaveCount(4, {
    timeout: 30000,
  });
  await page.goto(base + "/reports");
  await page.reload();
  await expect(page.locator(".balance-chart").first()).toBeVisible({
    timeout: 30000,
  });
  const r = await page.request.get(base + "/api/v1/me");
  expect(r.status()).toBe(200);
  expect(r.headers()["cache-control"]).toContain("no-store");
  await page.screenshot({
    path: `test-results/deployment-${info.project.name}.png`,
    fullPage: true,
  });
});
