import Link from "next/link";
import type { ReactNode } from "react";

/**
 * Page chrome: a sticky-footer shell with a branded header and footer. Widths
 * are constrained with a centered max-width container so content stays
 * readable on large screens.
 */
export function Layout({ children }: { children: ReactNode }) {
  const year = new Date().getFullYear();

  return (
    <>
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <Link
            href="/"
            className="text-lg font-bold tracking-tight text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          >
            News<span className="text-brand">Line</span>
          </Link>
          <span className="text-sm text-muted">Latest stories</span>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl flex-1 px-6 py-10">{children}</main>

      <footer className="border-t border-border bg-surface">
        <div className="mx-auto max-w-6xl px-6 py-6 text-sm text-muted">
          © {year} News Line
        </div>
      </footer>
    </>
  );
}
