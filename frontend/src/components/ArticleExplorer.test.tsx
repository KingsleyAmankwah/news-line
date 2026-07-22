import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ArticleExplorer } from "@/components/ArticleExplorer";
import type { Article } from "@/lib/types";

const make = (id: string, title: string, slug: string, name: string): Article => ({
  id,
  type: "article",
  title,
  path: `/articles/${id}`,
  summary: "",
  promoted: false,
  sticky: false,
  readingTimeMinutes: 1,
  publishedAt: "2024-01-01T00:00:00+00:00",
  updatedAt: "2024-01-01T00:00:00+00:00",
  author: null,
  category: { id: slug, name, slug },
  tags: [],
  hero: null,
});

const articles = [
  make("1", "Alpha", "environment", "Environment"),
  make("2", "Beta", "engineering", "Engineering"),
  make("3", "Gamma", "environment", "Environment"),
];

describe("ArticleExplorer", () => {
  // Verifies the initial render shows every article and derives one filter
  // button per unique category (deduped) plus an "All" button.
  it("renders all articles and a filter per category", () => {
    render(<ArticleExplorer articles={articles} />);

    expect(screen.getByRole("link", { name: "Alpha" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Beta" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Gamma" })).toBeInTheDocument();

    expect(screen.getByRole("button", { name: "All" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Environment" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Engineering" })).toBeInTheDocument();
  });

  // Verifies the core interaction: clicking a category filters the rendered
  // set to only that category's articles (useState + useMemo working together).
  it("filters articles when a category is selected", async () => {
    render(<ArticleExplorer articles={articles} />);

    await userEvent.click(screen.getByRole("button", { name: "Environment" }));

    expect(screen.getByRole("link", { name: "Alpha" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Gamma" })).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Beta" })).not.toBeInTheDocument();
  });

  // Verifies the active filter is communicated to assistive tech via
  // aria-pressed, and only one filter is active at a time.
  it("marks the active category with aria-pressed", async () => {
    render(<ArticleExplorer articles={articles} />);

    const engineering = screen.getByRole("button", { name: "Engineering" });
    await userEvent.click(engineering);

    expect(engineering).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByRole("button", { name: "All" })).toHaveAttribute("aria-pressed", "false");
  });

  // Verifies clicking "All" clears the filter and restores the full set.
  it("restores all articles when All is clicked", async () => {
    render(<ArticleExplorer articles={articles} />);

    await userEvent.click(screen.getByRole("button", { name: "Engineering" }));
    expect(screen.queryByRole("link", { name: "Alpha" })).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: "All" }));
    expect(screen.getByRole("link", { name: "Alpha" })).toBeInTheDocument();
  });
});
