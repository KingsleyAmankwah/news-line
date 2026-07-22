/**
 * Loading placeholder for the article grid. Rendered as the Suspense fallback
 * while the feed streams in, and reused by the route-level loading UI.
 */
export function ArticleGridSkeleton({ count = 6 }: { count?: number }) {
  return (
    <div
      className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
      aria-hidden="true"
      data-testid="article-grid-skeleton"
    >
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={index}
          className="overflow-hidden rounded-card border border-border bg-surface"
        >
          <div className="aspect-video animate-pulse bg-accent" />
          <div className="flex flex-col gap-3 p-5">
            <div className="h-3 w-24 animate-pulse rounded bg-accent" />
            <div className="h-5 w-full animate-pulse rounded bg-accent" />
            <div className="h-4 w-3/4 animate-pulse rounded bg-accent" />
          </div>
        </div>
      ))}
    </div>
  );
}
