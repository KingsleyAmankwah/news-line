import { cn } from "@/lib/utils";

/**
 * Brand logo: a newspaper mark plus the "News Line" wordmark. The mark is an
 * inline SVG using currentColor so it inherits the brand colour and works in
 * both themes without an external asset.
 */
export function Logo({ className }: { className?: string }) {
  return (
    <span className={cn("inline-flex items-center gap-2", className)}>
      <svg
        viewBox="0 0 24 24"
        className="h-7 w-7 text-brand"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        <path d="M4 4h13a1 1 0 0 1 1 1v14a2 2 0 0 0 2 2H6a2 2 0 0 1-2-2V4z" />
        <path d="M18 8h2a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2" />
        <line x1="7" y1="8" x2="14" y2="8" />
        <line x1="7" y1="12" x2="14" y2="12" />
        <line x1="7" y1="16" x2="11" y2="16" />
      </svg>
      <span className="text-lg font-bold tracking-tight text-foreground">
        News<span className="text-brand">Line</span>
      </span>
    </span>
  );
}
