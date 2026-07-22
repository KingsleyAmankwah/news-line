import { ArticleExplorer } from "@/components/ArticleExplorer";
import { getArticleFeed } from "@/lib/api";

/**
 * Async server component that loads the feed. It is rendered inside a
 * <Suspense> boundary on the home page, so the page shell streams immediately
 * while this awaits data behind the skeleton fallback. If the fetch throws, the
 * route's error boundary (error.tsx) handles it.
 */
export async function ArticleFeedSection() {
  const feed = await getArticleFeed({ itemsPerPage: 12 });

  if (feed.data.length === 0) {
    return (
      <p className="rounded-card border border-border bg-surface p-6 text-muted">
        No stories have been published yet.
      </p>
    );
  }

  return <ArticleExplorer articles={feed.data} />;
}
