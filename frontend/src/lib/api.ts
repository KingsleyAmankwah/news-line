import "server-only";

import { unstable_cache } from "next/cache";
import { getAccessToken } from "@/lib/auth";
import { toBackendUrl } from "@/lib/backend-url";
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

function withBackendHero<T extends { hero: HeroImage | null }>(article: T): T {
  if (!article.hero) {
    return article;
  }
  const base = drupalBaseUrl();
  return {
    ...article,
    hero: {
      ...article.hero,
      src: toBackendUrl(article.hero.src, base) ?? article.hero.src,
      thumbnail: toBackendUrl(article.hero.thumbnail, base),
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

const EMPTY_FEED: FeedResponse = {
  data: [],
  meta: { count: 0, page: 0, itemsPerPage: 0, totalPages: 0 },
  links: { self: "", next: null, prev: null },
};

export async function getArticleFeed(query: FeedQuery = {}): Promise<FeedResponse> {
  const load = unstable_cache(
    () => fetchArticleFeed(query),
    ["article-feed", JSON.stringify(query)],
    { revalidate: revalidateSeconds(), tags: ["article-feed"] },
  );

  try {
    return await load();
  } catch (error) {
    // The backend may be briefly unreachable (e.g. a demo tunnel is down).
    // Degrade to an empty feed so a build/prerender never hard-fails; the next
    // revalidation refetches once the backend is back.
    console.error("Article feed unavailable:", error);
    return EMPTY_FEED;
  }
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
