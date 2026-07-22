import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { Layout } from "@/components/Layout";
import { getArticle, getArticleFeed } from "@/lib/api";
import { formatDate } from "@/lib/utils";

export const revalidate = 60;

interface ArticlePageProps {
  params: Promise<{ slug: string }>;
}

/**
 * Prerender detail pages for the articles currently in the feed. Articles
 * published later aren't listed here but still render on demand (and are then
 * cached), because dynamicParams defaults to true.
 */
export async function generateStaticParams(): Promise<Array<{ slug: string }>> {
  try {
    const feed = await getArticleFeed({ itemsPerPage: 50 });
    return feed.data
      .map((article) => article.path.replace(/^\/articles\//, ""))
      .filter((slug) => slug !== "")
      .map((slug) => ({ slug }));
  } catch {
    return [];
  }
}

export async function generateMetadata({ params }: ArticlePageProps): Promise<Metadata> {
  const { slug } = await params;
  const article = await getArticle(slug).catch(() => null);

  if (!article) {
    return { title: "Article not found — News Line" };
  }
  return {
    title: `${article.title} — News Line`,
    description: article.summary || undefined,
  };
}

export default async function ArticlePage({ params }: ArticlePageProps) {
  const { slug } = await params;

  const article = await getArticle(slug).catch(() => null);
  if (!article) {
    notFound();
  }

  return (
    <Layout>
      <article className="mx-auto max-w-3xl">
        <Link
          href="/"
          className="text-sm text-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          &larr; Back to articles
        </Link>

        <div className="mt-6 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
          {article.category ? (
            <span className="rounded-full bg-accent px-2.5 py-1 font-medium text-brand">
              {article.category.name}
            </span>
          ) : null}
          <span>{article.readingTimeMinutes} min read</span>
          {article.publishedAt ? (
            <>
              <span aria-hidden>·</span>
              <time dateTime={article.publishedAt}>{formatDate(article.publishedAt)}</time>
            </>
          ) : null}
        </div>

        <h1 className="mt-3 text-4xl font-bold leading-tight tracking-tight text-foreground">
          {article.title}
        </h1>
        {article.author ? (
          <p className="mt-2 text-sm text-muted">By {article.author.name}</p>
        ) : null}

        {article.hero ? (
          <div className="relative mt-8 aspect-video overflow-hidden rounded-card bg-accent">
            <Image
              src={article.hero.src}
              alt={article.hero.alt}
              fill
              sizes="(max-width: 768px) 100vw, 768px"
              className="object-cover"
              priority
            />
          </div>
        ) : null}

        {/* Body is HTML already filtered by Drupal's text format (trusted, sanitized backend). */}
        <div
          className="article-body mt-8 text-foreground"
          dangerouslySetInnerHTML={{ __html: article.body }}
        />

        {article.tags.length > 0 ? (
          <div className="mt-10 flex flex-wrap gap-2 border-t border-border pt-6">
            {article.tags.map((tag) => (
              <span
                key={tag.id}
                className="rounded-full border border-border px-2.5 py-1 text-xs text-muted"
              >
                #{tag.slug}
              </span>
            ))}
          </div>
        ) : null}
      </article>
    </Layout>
  );
}
