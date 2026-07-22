import { toBackendUrl } from "@/lib/backend-url";

describe("toBackendUrl", () => {
  // The bug that shipped: a backend-emitted port (:33000) leaked through because
  // the URL `host` setter doesn't clear a port. This is the regression guard.
  it("strips a backend port when the base origin has none", () => {
    expect(
      toBackendUrl("https://cms.example:33000/sites/x.jpg?itok=a", "https://cms.example"),
    ).toBe("https://cms.example/sites/x.jpg?itok=a");
  });

  it("keeps the base's port for local development", () => {
    expect(toBackendUrl("http://localhost/sites/x.jpg", "http://cms.ddev.site:33000")).toBe(
      "http://cms.ddev.site:33000/sites/x.jpg",
    );
  });

  it("overrides an unreliable absolute host onto the base origin", () => {
    expect(toBackendUrl("http://localhost/sites/x.jpg?itok=a", "https://cms.example")).toBe(
      "https://cms.example/sites/x.jpg?itok=a",
    );
  });

  it("resolves a relative path against the base", () => {
    expect(toBackendUrl("/sites/x.jpg?itok=a", "https://cms.example")).toBe(
      "https://cms.example/sites/x.jpg?itok=a",
    );
  });

  it("preserves the itok query used by image styles", () => {
    const result = toBackendUrl("https://cms.example:33000/f.jpg?itok=SECRET", "https://cms.example");
    expect(result).toContain("itok=SECRET");
  });

  it("passes null through unchanged", () => {
    expect(toBackendUrl(null, "https://cms.example")).toBeNull();
  });
});
