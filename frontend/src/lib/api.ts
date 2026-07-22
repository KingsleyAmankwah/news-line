import "server-only";

import { unstable_cache } from "next/cache";
import { getAccessToken } from "@/lib/auth";
import { normalizeArticleDetail, normalizeFeedResponse } from "@/lib/parse-feed";
import type { ArticleDetail, FeedQuery, FeedResponse, HeroImage } from "@/lib/types";

/**
 * Server-only client for the Drupal Article Feed API.
 *
 * The token exchange and feed request are wrapped in a cache boundary so the
 * result is cached with Incremental Static Regeneration (a revalidate window
 * plus an "article-feed" tag a future on-demand webhook can invalidate). This
 * keeps the calling page statically generated — an uncached token POST during
 * render would otherwise force the whole route to render dynamically — and
 * caches only the shaped feed, never the access token.
 */

function drupalBaseUrl(): string {
  return (process.env.DRUPAL_BASE_URL ?? "http://news-line.ddev.site:33000").replace(
    /\/+$/,
    "",
  );
}

function revalidateSeconds(): number {
  const parsed = Number(process.env.FEED_REVALIDATE_SECONDS);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : 60;
}

/**
 * Forces an image URL onto the configured backend origin.
 *
 * Drupal emits image-style URLs whose host reflects however the request
 * reached it (behind DDEV/a proxy that can be "localhost"), which is
 * unreliable for a decoupled client. The frontend owns the public backend
 * location, so it rewrites the origin — resolving relative paths and
 * overriding any absolute host — keeping the path and the required itok query.
 */
function toBackendUrl(rawUrl: string | null): string | null {
  if (!rawUrl) {
    return rawUrl;
  }
  try {
    const base = new URL(drupalBaseUrl());
    const resolved = new URL(rawUrl, base);
    resolved.protocol = base.protocol;
    resolved.host = base.host;
    return resolved.toString();
  } catch {
    return rawUrl;
  }
}

function withBackendHero<T extends { hero: HeroImage | null }>(article: T): T {
  if (!article.hero) {
    return article;
  }
  return {
    ...article,
    hero: {
      ...article.hero,
      src: toBackendUrl(article.hero.src) ?? article.hero.src,
      thumbnail: toBackendUrl(article.hero.thumbnail),
    },
  };
}

async function fetchArticleFeed(query: FeedQuery): Promise<FeedResponse> {
  const params = new URLSearchParams({ _format: "json" });
  if (query.page !== undefined) {
    params.set("page", String(query.page));
  }
  if (query.itemsPerPage !== undefined) {
    params.set("items_per_page", String(query.itemsPerPage));
  }
  if (query.category) {
    params.set("category", query.category);
  }

  const token = await getAccessToken();
  const response = await fetch(`${drupalBaseUrl()}/api/article-feed?${params.toString()}`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error(`Article feed request failed with status ${response.status}.`);
  }

  const feed = normalizeFeedResponse(await response.json());
  return { ...feed, data: feed.data.map(withBackendHero) };
}

export async function getArticleFeed(query: FeedQuery = {}): Promise<FeedResponse> {
  const load = unstable_cache(
    () => fetchArticleFeed(query),
    ["article-feed", JSON.stringify(query)],
    { revalidate: revalidateSeconds(), tags: ["article-feed"] },
  );

  return load();
}

async function fetchArticle(slug: string): Promise<ArticleDetail | null> {
  const token = await getAccessToken();
  const response = await fetch(
    `${drupalBaseUrl()}/api/article/${encodeURIComponent(slug)}?_format=json`,
    {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
      cache: "no-store",
    },
  );

  if (response.status === 404) {
    return null;
  }
  if (!response.ok) {
    throw new Error(`Article request failed with status ${response.status}.`);
  }

  const article = normalizeArticleDetail(await response.json());
  return article ? withBackendHero(article) : null;
}

export async function getArticle(slug: string): Promise<ArticleDetail | null> {
  const load = unstable_cache(
    () => fetchArticle(slug),
    ["article", slug],
    { revalidate: revalidateSeconds(), tags: ["article-feed", `article:${slug}`] },
  );

  return load();
}
