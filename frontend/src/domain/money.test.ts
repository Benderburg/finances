import { describe, it, expect } from "vitest";
import { parseMoney, formatMoney, quoteTarget } from "./money";
describe("exact money at boundaries", () => {
  it("parses locale input without rounding", () => {
    expect(parseMoney("1 000,25")).toBe("100025");
    expect(() => parseMoney("0.001")).toThrow();
    expect(() => parseMoney("0")).toThrow();
    expect(parseMoney("0", true)).toBe("0");
  });
  it("keeps the maximum exact and rejects overflow", () => {
    expect(parseMoney("9999999999999.99")).toBe("999999999999999");
    expect(() => parseMoney("10000000000000")).toThrow();
    expect(formatMoney("999999999999999", "MDL", "en")).toBe(
      "9,999,999,999,999.99 MDL",
    );
  });
  it("quotes and rounds half up once", () => {
    expect(quoteTarget("10000", "3")).toBe("3333");
    expect(quoteTarget("1", "2")).toBe("1");
    expect(() => quoteTarget("1", "3")).toThrow();
  });
});
