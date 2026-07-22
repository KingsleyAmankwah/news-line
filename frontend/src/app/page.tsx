import { Card } from "@/components/Card";
import { Layout } from "@/components/Layout";
import { getArticleFeed } from "@/lib/api";
import type { FeedResponse } from "@/lib/types";

// Incremental Static Regeneration: the page is served statically and
// re-generated in the background at most once per revalidation window.
export const revalidate = 60;

export default async function HomePage() {
  let feed: FeedResponse | null = null;

  try {
    feed = await getArticleFeed({ itemsPerPage: 12 });
  } catch {
    // A backend outage or misconfiguration must not crash the page; fall
    // through to the unavailable state below.
    feed = null;
  }

  return (
    <Layout>
      <div className="mb-8">
        <h1 className="text-3xl font-bold tracking-tight text-foreground">Latest stories</h1>
        <p className="mt-2 text-muted">
          Reporting and features from the News Line desk.
        </p>
      </div>

      {!feed ? (
        <p className="rounded-card border border-border bg-surface p-6 text-muted">
          The article feed is currently unavailable. Please try again shortly.
        </p>
      ) : feed.data.length === 0 ? (
        <p className="rounded-card border border-border bg-surface p-6 text-muted">
          No articles have been published yet.
        </p>
      ) : (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {feed.data.map((article) => (
            <Card key={article.id} article={article} />
          ))}
        </div>
      )}
    </Layout>
  );
}
