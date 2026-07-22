"use client";

import { Button } from "@/components/Button";

/**
 * Route error boundary. React renders this Client Component when a child
 * (e.g. the feed section) throws during render, and `reset` retries the
 * segment — the idiomatic React 18 alternative to inline try/catch.
 */
export default function FeedError({ reset }: { error: Error; reset: () => void }) {
  return (
    <main className="mx-auto flex max-w-3xl flex-col items-start gap-4 px-6 py-16">
      <h1 className="text-2xl font-bold tracking-tight text-foreground">
        Something went wrong
      </h1>
      <p className="text-muted">
        The article feed is currently unavailable. Please try again.
      </p>
      <Button onClick={reset}>Try again</Button>
    </main>
  );
}
