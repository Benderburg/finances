import { createServer } from "node:http";
import { readFileSync } from "node:fs";
import { test, expect } from "./fixtures";

test("PWA install refreshes the shell and icons previously cached by the browser", async ({
  page,
}) => {
  const worker = readFileSync("../backend/resources/pwa/sw.js", "utf8");
  const cacheName = worker.match(/const CACHE='([^']+)'/)![1];
  let generation = "old";
  const requests = new Map<string, number>();
  const server = createServer((request, response) => {
    const path = new URL(request.url!, "http://localhost").pathname;
    requests.set(path, (requests.get(path) ?? 0) + 1);
    if (path === "/probe") {
      response.writeHead(200, {
        "Content-Type": "text/html",
        "Cache-Control": "no-store",
      });
      response.end(
        "<!doctype html><title>PWA cache QA</title><h1>PWA cache QA</h1>",
      );
    } else if (path === "/sw.js") {
      response.writeHead(200, {
        "Content-Type": "application/javascript",
        "Cache-Control": "no-cache",
      });
      response.end(worker);
    } else {
      // Match Timeweb's long-lived HTTP cache for existing static resources.
      response.writeHead(200, { "Cache-Control": "max-age=31536000" });
      response.end(`${generation}:${path}`);
    }
  });
  await new Promise<void>((resolve) => server.listen(0, "127.0.0.1", resolve));
  const address = server.address();
  if (!address || typeof address === "string")
    throw new Error("QA server not started");
  try {
    await page.goto(`http://127.0.0.1:${address.port}/probe`);
    const warm = () =>
      page.evaluate(async () =>
        Promise.all(
          ["/build/index.html", "/icons/icon-192.png"].map(async (path) =>
            (await fetch(path, { cache: "force-cache" })).text(),
          ),
        ),
      );
    expect(await warm()).toEqual([
      "old:/build/index.html",
      "old:/icons/icon-192.png",
    ]);
    generation = "fresh";
    expect(await warm()).toEqual([
      "old:/build/index.html",
      "old:/icons/icon-192.png",
    ]);
    expect(requests.get("/build/index.html")).toBe(1);
    expect(requests.get("/icons/icon-192.png")).toBe(1);
    await page.evaluate(async () => {
      await navigator.serviceWorker.register("/sw.js");
      await navigator.serviceWorker.ready;
    });
    const cached = await page.evaluate(async (name) => {
      const cache = await caches.open(name);
      return Promise.all(
        ["/build/index.html", "/icons/icon-192.png"].map(async (path) =>
          (await cache.match(path))!.text(),
        ),
      );
    }, cacheName);
    expect(cached).toEqual([
      "fresh:/build/index.html",
      "fresh:/icons/icon-192.png",
    ]);
    expect(requests.get("/build/index.html")).toBe(2);
    expect(requests.get("/icons/icon-192.png")).toBe(2);
  } finally {
    server.closeAllConnections();
    await new Promise<void>((resolve, reject) =>
      server.close((error) => (error ? reject(error) : resolve())),
    );
  }
});
