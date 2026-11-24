import { describe, expect, it } from "vitest";
import { apiUrl } from "./api";

describe("apiUrl", () => {
  it("préfixe les routes avec /api/v1", () => {
    expect(apiUrl("public/salon-ama")).toBe("http://localhost:8000/api/v1/public/salon-ama");
  });

  it("ignore le slash initial", () => {
    expect(apiUrl("/me")).toBe("http://localhost:8000/api/v1/me");
  });
});
