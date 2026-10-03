import type { Dashboard } from "../domain/types";

export interface Summary {
  user_id: string;
  revision: number;
  saved_at: string;
  balances: Dashboard["balances"];
  cash_flow: Dashboard["cash_flow"];
  month: string;
}
function database(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const r = indexedDB.open("norocel-summary", 1);
    r.onupgradeneeded = () =>
      r.result.createObjectStore("summaries", { keyPath: "user_id" });
    r.onsuccess = () => resolve(r.result);
    r.onerror = () => reject(r.error);
  });
}
export async function saveSummary(
  user: string,
  revision: number,
  d: Dashboard,
) {
  const db = await database();
  if (
    localStorage.getItem("norocel-summary-owner") !== user ||
    localStorage.getItem("norocel-summary-opt-in:" + user) !== "1" ||
    localStorage.getItem("norocel-pending-logout") === "1"
  ) {
    db.close();
    return;
  }
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction("summaries", "readwrite");
    tx.objectStore("summaries").put({
      user_id: user,
      revision,
      saved_at: new Date().toISOString(),
      balances: d.balances,
      cash_flow: d.cash_flow,
      month: d.month,
    } satisfies Summary);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
  db.close();
}
export async function loadSummary(user: string): Promise<Summary | undefined> {
  const db = await database();
  const result = await new Promise<Summary | undefined>((resolve, reject) => {
    const r = db.transaction("summaries").objectStore("summaries").get(user);
    r.onsuccess = () => resolve(r.result);
    r.onerror = () => reject(r.error);
  });
  db.close();
  return result;
}
export async function clearSummaries() {
  localStorage.removeItem("norocel-summary-owner");
  const db = await database();
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction("summaries", "readwrite");
    tx.objectStore("summaries").clear();
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
  db.close();
}
