import { describe, expect, it } from "vitest";
import { formatDateTime, formatXof } from "./format";

describe("formatXof", () => {
  it("formate un entier en FCFA sans décimales", () => {
    expect(formatXof(10000)).toBe("10 000 FCFA");
    expect(formatXof(0)).toBe("0 FCFA");
  });

  it("refuse les montants non entiers", () => {
    expect(() => formatXof(1000.5)).toThrow(RangeError);
  });
});

describe("formatDateTime", () => {
  it("affiche un instant UTC à l'heure d'Abidjan (UTC+0)", () => {
    expect(formatDateTime("2026-12-02T09:30:00Z")).toContain("09:30");
  });

  it("n'applique pas le fuseau de la machine", () => {
    const previous = process.env.TZ;
    process.env.TZ = "Pacific/Auckland";
    try {
      expect(formatDateTime("2026-12-02T09:30:00Z")).toContain("09:30");
    } finally {
      if (previous === undefined) delete process.env.TZ;
      else process.env.TZ = previous;
    }
  });
});
