import "server-only";

import { unstable_cache } from "next/cache";
import { getAccessToken } from "@/lib/auth";
import { normalizeArticleDetail, normalizeFeedResponse } from "@/lib/parse-feed";
import type { ArticleDetail, FeedQuery, FeedResponse } from "@/lib/types";

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

  return normalizeFeedResponse(await response.json());
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

  return normalizeArticleDetail(await response.json());
}

export async function getArticle(slug: string): Promise<ArticleDetail | null> {
  const load = unstable_cache(
    () => fetchArticle(slug),
    ["article", slug],
    { revalidate: revalidateSeconds(), tags: ["article-feed", `article:${slug}`] },
  );

  return load();
}
