import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { test as base, expect } from "@playwright/test";

export const test = base.extend<{ isolatedCache: void }>({
  isolatedCache: [
    async ({}, use) => {
      if (process.env.NOROCEL_E2E_RESET_CACHE === "1") {
        const origin = new URL(process.env.NOROCEL_QA_URL ?? "http://127.0.0.1:8000");
        const backend = new URL("../../backend/", import.meta.url);
        const environment = readFileSync(new URL(".env", backend), "utf8");
        if (origin.hostname !== "127.0.0.1" || !/^APP_ENV=local\r?$/m.test(environment)) {
          throw new Error("Cache isolation requires a local test server and APP_ENV=local.");
        }
        // A fast CI runner can exhaust a shared IP's auth limit between tests.
        // Clear only the local fixture cache; keep the application's limits intact.
        execFileSync("php", ["artisan", "cache:clear"], {
          cwd: fileURLToPath(backend),
          stdio: "pipe",
        });
      }
      await use();
    },
    { auto: true },
  ],
});

export { expect };
export type { Page } from "@playwright/test";
