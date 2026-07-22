"use client";

import { useMemo, useState } from "react";
import { Button } from "@/components/Button";
import { Card } from "@/components/Card";
import type { Article } from "@/lib/types";

/**
 * Client-side article explorer.
 *
 * Receives the server-fetched articles as props (so the page stays statically
 * generated / ISR) and adds interactivity with React hooks: useState holds the
 * active category, and useMemo derives the category list and the filtered set
 * without recomputing on every render. Composes the shared Button and Card
 * components rather than re-implementing them.
 */
export function ArticleExplorer({ articles }: { articles: Article[] }) {
  const [activeCategory, setActiveCategory] = useState<string | null>(null);

  const categories = useMemo(() => {
    const seen = new Map<string, string>();
    for (const article of articles) {
      if (article.category) {
        seen.set(article.category.slug, article.category.name);
      }
    }
    return Array.from(seen, ([slug, name]) => ({ slug, name }));
  }, [articles]);

  const filtered = useMemo(
    () =>
      activeCategory === null
        ? articles
        : articles.filter((article) => article.category?.slug === activeCategory),
    [articles, activeCategory],
  );

  return (
    <div>
      <div
        className="mb-8 flex flex-wrap gap-2"
        role="group"
        aria-label="Filter stories by category"
      >
        <Button
          size="sm"
          variant={activeCategory === null ? "primary" : "secondary"}
          aria-pressed={activeCategory === null}
          onClick={() => setActiveCategory(null)}
        >
          All
        </Button>
        {categories.map((category) => (
          <Button
            key={category.slug}
            size="sm"
            variant={activeCategory === category.slug ? "primary" : "secondary"}
            aria-pressed={activeCategory === category.slug}
            onClick={() => setActiveCategory(category.slug)}
          >
            {category.name}
          </Button>
        ))}
      </div>

      {filtered.length === 0 ? (
        <p className="rounded-card border border-border bg-surface p-6 text-muted">
          No stories in this category yet.
        </p>
      ) : (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((article) => (
            <Card key={article.id} article={article} />
          ))}
        </div>
      )}
    </div>
  );
}
