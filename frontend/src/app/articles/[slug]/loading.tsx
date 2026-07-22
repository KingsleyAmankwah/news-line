import { Layout } from "@/components/Layout";

/**
 * Route-level loading UI for an article. Next renders this as the Suspense
 * fallback while the detail page loads, so navigation shows an instant
 * skeleton instead of a blank screen.
 */
export default function ArticleLoading() {
  return (
    <Layout>
      <div className="mx-auto max-w-3xl" aria-hidden="true">
        <div className="h-4 w-32 animate-pulse rounded bg-accent" />
        <div className="mt-6 h-3 w-40 animate-pulse rounded bg-accent" />
        <div className="mt-3 h-10 w-full animate-pulse rounded bg-accent" />
        <div className="mt-8 aspect-video w-full animate-pulse rounded-card bg-accent" />
        <div className="mt-8 space-y-3">
          <div className="h-4 w-full animate-pulse rounded bg-accent" />
          <div className="h-4 w-full animate-pulse rounded bg-accent" />
          <div className="h-4 w-2/3 animate-pulse rounded bg-accent" />
        </div>
      </div>
    </Layout>
  );
}
