import type { Currency, Locale } from "./types";

export const currencies: Currency[] = ["MDL", "EUR", "USD", "RON"];
export const maxMinor = 999999999999999n;
export function parseMoney(value: string, allowZero = false): string {
  const plain = value.trim().replace(/[\s\u00a0\u202f]/g, "");
  if (!/^(?:0|[1-9]\d*)(?:[.,]\d{1,2})?$/.test(plain))
    throw new Error("INVALID_MONEY");
  const [whole, fraction = ""] = plain.split(/[.,]/);
  const amount = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0"));
  if ((!allowZero && amount === 0n) || amount > maxMinor)
    throw new Error("INVALID_MONEY");
  return amount.toString();
}
export function inputMoney(minor: string): string {
  const n = BigInt(minor);
  const sign = n < 0n ? "-" : "";
  const a = n < 0n ? -n : n;
  return `${sign}${a / 100n}.${(a % 100n).toString().padStart(2, "0")}`;
}
export function formatMoney(
  minor: string,
  currency: Currency,
  locale: Locale = "ro",
): string {
  const n = BigInt(minor);
  const a = n < 0n ? -n : n;
  const group = new Intl.NumberFormat(locale).format(a / 100n);
  const decimal = locale === "en" ? "." : ",";
  return `${n < 0n ? "−" : ""}${group}${decimal}${(a % 100n).toString().padStart(2, "0")} ${currency}`;
}
export function quoteTarget(source: string, rate: string): string {
  if (!/^(?:0|[1-9]\d*)(?:[.,]\d{1,12})?$/.test(rate))
    throw new Error("FX_RATE_OUT_OF_RANGE");
  const [whole, fraction = ""] = rate.replace(",", ".").split(".");
  const numerator =
    BigInt(whole) * 10n ** 12n + BigInt(fraction.padEnd(12, "0"));
  if (numerator === 0n) throw new Error("FX_RATE_OUT_OF_RANGE");
  const dividend = BigInt(source) * 10n ** 12n;
  const target = (dividend + numerator / 2n) / numerator;
  if (target === 0n || target > maxMinor) throw new Error("INVALID_MONEY");
  return target.toString();
}
export function today(timezone: string): string {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: timezone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date());
}
