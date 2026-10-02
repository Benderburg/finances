import { afterEach, expect, test, vi } from "vitest";
import { getAll } from "./api";

afterEach(() => vi.unstubAllGlobals());

test("reference lookup includes accounts beyond page one and preserves filters", async () => {
  const first = Array.from({ length: 100 }, (_, index) => ({
    id: String(index),
    name: `Account ${index}`,
  }));
  const fetchMock = vi.fn(async (path: string) => {
    const params = new URL(path, "http://localhost").searchParams;
    expect(params.get("kind")).toBe("savings");
    expect(params.get("archive")).toBe("active");
    expect(params.get("per_page")).toBe("100");
    const page = Number(params.get("page"));
    return {
      ok: true,
      json: async () => ({
        data: {
          items: page === 1 ? first : [{ id: "100", name: "Goal savings" }],
          pagination: { page, pages: 2, total: 101 },
        },
        meta: { workspace_revision: 5 },
      }),
    };
  });
  vi.stubGlobal("fetch", fetchMock);
  const result = await getAll<{ id: string; name: string }>(
    "/accounts?kind=savings&archive=active",
  );
  expect(result.data.items).toHaveLength(101);
  expect(result.data.items.find((account) => account.id === "100")?.name).toBe(
    "Goal savings",
  );
  expect(fetchMock).toHaveBeenCalledTimes(2);
});
