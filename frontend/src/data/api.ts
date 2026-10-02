import type { Envelope, List } from "../domain/types";

export class ApiError extends Error {
  constructor(
    public code: string,
    public status: number,
    public fields: Record<string, string[]> = {},
    public details: Record<string, unknown> = {},
  ) {
    super(code);
  }
}
const base = import.meta.env.VITE_API_BASE_URL ?? "";
const xsrf = () =>
  decodeURIComponent(
    document.cookie
      .split("; ")
      .find((v) => v.startsWith("XSRF-TOKEN="))
      ?.slice(11) ?? "",
  );
export async function csrf(): Promise<void> {
  const r = await fetch(`${base}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
  if (!r.ok) throw new ApiError("CSRF_EXPIRED", r.status);
}
export async function request<T>(
  path: string,
  method = "GET",
  body?: unknown,
  key?: string,
): Promise<T> {
  if (method !== "GET" && !navigator.onLine) throw new ApiError("OFFLINE", 0);
  if (method !== "GET" && !xsrf()) await csrf();
  const form = body instanceof FormData;
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (body !== undefined && !form) headers["Content-Type"] = "application/json";
  if (method !== "GET") headers["X-XSRF-TOKEN"] = xsrf();
  if (key) headers["Idempotency-Key"] = key;
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 25000);
  let r: Response;
  let data;
  try {
    r = await fetch(base + path, {
      method,
      credentials: "include",
      headers,
      body: body === undefined ? undefined : form ? body : JSON.stringify(body),
      cache: "no-store",
      signal: controller.signal,
    });
    data = await r.json();
  } catch {
    throw new ApiError("NETWORK_UNKNOWN", 0);
  } finally {
    clearTimeout(timeout);
  }
  if (!r.ok) {
    const e = data.error ?? {};
    if (r.status === 401 && path !== "/api/v1/me") {
      window.dispatchEvent(new Event("norocel-session-expired"));
    }
    throw new ApiError(
      e.code ?? "REQUEST_FAILED",
      r.status,
      e.fields,
      e.details,
    );
  }
  return data as T;
}
export const get = <T>(path: string) => request<Envelope<T>>("/api/v1" + path);
export async function getAll<T>(path: string): Promise<Envelope<List<T>>> {
  const [resource, query] = path.split("?");
  const params = new URLSearchParams(query);
  params.set("per_page", "100");
  params.set("page", "1");
  const first = await get<List<T>>(resource + "?" + params);
  const items = [...first.data.items];
  for (let page = 2; page <= first.data.pagination.pages; page++) {
    params.set("page", String(page));
    const next = await get<List<T>>(resource + "?" + params);
    items.push(...next.data.items);
  }
  return { ...first, data: { ...first.data, items } };
}
export const command = <T>(
  path: string,
  method: string,
  body: unknown,
  key: string,
) => request<Envelope<T>>("/api/v1" + path, method, body, key);
export function download(path: string) {
  const a = document.createElement("a");
  a.href = base + path;
  a.download = "";
  document.body.append(a);
  a.click();
  a.remove();
}
