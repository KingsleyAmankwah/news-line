import { render, screen } from "@testing-library/react";
import { Card } from "@/components/Card";
import type { Article } from "@/lib/types";

const article: Article = {
  id: "1",
  type: "article",
  title: "Wetlands Return",
  path: "/articles/wetlands-return",
  summary: "A short teaser about the marshes.",
  promoted: false,
  sticky: false,
  readingTimeMinutes: 6,
  publishedAt: "2023-03-14T00:00:00+00:00",
  updatedAt: "2023-03-14T00:00:00+00:00",
  author: { id: "a", name: "Kingsley A." },
  category: { id: "c", name: "Environment", slug: "environment" },
  tags: [],
  hero: {
    alt: "A marsh at dawn",
    width: 1200,
    height: 675,
    src: "https://cms.example/sites/default/files/styles/feed_hero/x.jpg",
    thumbnail: "https://cms.example/sites/default/files/styles/feed_thumbnail/x.jpg",
  },
};

describe("Card", () => {
  // Verifies the title is a link pointing at the article's path — the whole
  // card is a navigation target, so the href must be correct.
  it("links the title to the article path", () => {
    render(<Card article={article} />);
    const link = screen.getByRole("link", { name: "Wetlands Return" });
    expect(link).toHaveAttribute("href", "/articles/wetlands-return");
  });

  // Verifies the key metadata (category, reading time, summary) is rendered.
  it("renders category, reading time and summary", () => {
    render(<Card article={article} />);
    expect(screen.getByText("Environment")).toBeInTheDocument();
    expect(screen.getByText("6 min read")).toBeInTheDocument();
    expect(screen.getByText("A short teaser about the marshes.")).toBeInTheDocument();
  });

  // Verifies the hero renders as an <img> exposing the alt text — an
  // accessibility requirement for meaningful images.
  it("renders the hero image with alt text", () => {
    render(<Card article={article} />);
    expect(screen.getByRole("img", { name: "A marsh at dawn" })).toBeInTheDocument();
  });

  // Verifies graceful degradation: no hero means no <img> element at all,
  // rather than a broken image.
  it("omits the image when there is no hero", () => {
    render(<Card article={{ ...article, hero: null }} />);
    expect(screen.queryByRole("img")).not.toBeInTheDocument();
  });
});
