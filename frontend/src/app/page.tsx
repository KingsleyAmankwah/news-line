import { Suspense } from "react";
import { ArticleFeedSection } from "@/components/ArticleFeedSection";
import { ArticleGridSkeleton } from "@/components/ArticleGridSkeleton";
import { Layout } from "@/components/Layout";

// Incremental Static Regeneration: served statically, re-generated in the
// background at most once per window.
export const revalidate = 60;

export default function HomePage() {
  return (
    <Layout>
      <div className="mb-8">
        <h1 className="text-3xl font-bold tracking-tight text-foreground">Latest stories</h1>
        <p className="mt-2 text-muted">Reporting and features from the News Line desk.</p>
      </div>

      {/* The shell renders immediately; the feed streams in behind the skeleton. */}
      <Suspense fallback={<ArticleGridSkeleton />}>
        <ArticleFeedSection />
      </Suspense>
    </Layout>
  );
}
