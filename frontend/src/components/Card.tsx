import Image from "next/image";
import Link from "next/link";
import type { Article } from "@/lib/types";
import { formatDate } from "@/lib/utils";

/**
 * Article card for the feed grid. The title link is stretched over the whole
 * card (via an absolutely-positioned ::after) so the entire card is a single
 * click target while remaining a semantically correct link.
 */
export function Card({ article }: { article: Article }) {
  const imageSrc = article.hero?.thumbnail ?? article.hero?.src ?? null;

  return (
    <article className="group relative flex flex-col overflow-hidden rounded-card border border-border bg-surface transition-shadow hover:shadow-lg">
      {article.hero && imageSrc ? (
        <div className="relative aspect-video overflow-hidden bg-accent">
          <Image
            src={imageSrc}
            alt={article.hero.alt}
            fill
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 400px"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
          />
        </div>
      ) : null}

      <div className="flex flex-1 flex-col gap-3 p-5">
        <div className="flex flex-wrap items-center gap-2 text-xs text-muted">
          {article.category ? (
            <span className="rounded-full bg-accent px-2.5 py-1 font-medium text-brand">
              {article.category.name}
            </span>
          ) : null}
          <span>{article.readingTimeMinutes} min read</span>
        </div>

        <h2 className="text-lg font-semibold leading-snug text-foreground">
          <Link
            href={article.path || "#"}
            className="after:absolute after:inset-0 focus-visible:outline-none"
          >
            {article.title}
          </Link>
        </h2>

        {article.summary ? (
          <p className="line-clamp-3 text-sm text-muted">{article.summary}</p>
        ) : null}

        <div className="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1 pt-2 text-xs text-muted">
          {article.author ? <span>{article.author.name}</span> : null}
          {article.author && article.publishedAt ? <span aria-hidden>·</span> : null}
          {article.publishedAt ? (
            <time dateTime={article.publishedAt}>{formatDate(article.publishedAt)}</time>
          ) : null}
        </div>
      </div>
    </article>
  );
}
