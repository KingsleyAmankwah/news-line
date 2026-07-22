import type {
  Article,
  ArticleDetail,
  AuthorRef,
  FeedLinks,
  FeedMeta,
  FeedResponse,
  HeroImage,
  TermRef,
} from "@/lib/types";

/**
 * Defensive parsing of the Article Feed payload.
 *
 * The API is our own, but a decoupled frontend must never assume a remote
 * response is well-formed: a schema change, an error page, or a partial
 * outage could return anything. Every field is validated and coerced, and a
 * malformed article is dropped rather than allowed to crash rendering.
 */

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function str(value: unknown, fallback = ""): string {
  return typeof value === "string" ? value : fallback;
}

function int(value: unknown, fallback = 0): number {
  return typeof value === "number" && Number.isFinite(value)
    ? Math.trunc(value)
    : fallback;
}

function bool(value: unknown): boolean {
  return value === true;
}

function nullableInt(value: unknown): number | null {
  return typeof value === "number" && Number.isFinite(value)
    ? Math.trunc(value)
    : null;
}

function parseTerm(value: unknown): TermRef | null {
  if (!isRecord(value)) {
    return null;
  }
  const id = str(value.id);
  const name = str(value.name);
  if (id === "" || name === "") {
    return null;
  }
  return { id, name, slug: str(value.slug) };
}

function parseAuthor(value: unknown): AuthorRef | null {
  if (!isRecord(value)) {
    return null;
  }
  const id = str(value.id);
  const name = str(value.name);
  return id === "" && name === "" ? null : { id, name };
}

function parseHero(value: unknown): HeroImage | null {
  if (!isRecord(value)) {
    return null;
  }
  const src = str(value.src);
  if (src === "") {
    return null;
  }
  return {
    alt: str(value.alt),
    width: nullableInt(value.width),
    height: nullableInt(value.height),
    src,
    thumbnail: typeof value.thumbnail === "string" ? value.thumbnail : null,
  };
}

function parseArticle(value: unknown): Article | null {
  if (!isRecord(value)) {
    return null;
  }
  const id = str(value.id);
  const title = str(value.title);
  // An article without an identity or title is unusable; skip it.
  if (id === "" || title === "") {
    return null;
  }
  const tags = Array.isArray(value.tags)
    ? value.tags.map(parseTerm).filter((tag): tag is TermRef => tag !== null)
    : [];

  return {
    id,
    type: "article",
    title,
    path: str(value.path),
    summary: str(value.summary),
    promoted: bool(value.promoted),
    sticky: bool(value.sticky),
    readingTimeMinutes: Math.max(1, int(value.readingTimeMinutes, 1)),
    publishedAt: str(value.publishedAt),
    updatedAt: str(value.updatedAt),
    author: parseAuthor(value.author),
    category: parseTerm(value.category),
    tags,
    hero: parseHero(value.hero),
  };
}

function parseMeta(value: unknown, articleCount: number): FeedMeta {
  const meta = isRecord(value) ? value : {};
  const itemsPerPage = Math.max(1, int(meta.itemsPerPage, articleCount || 1));
  return {
    count: int(meta.count, articleCount),
    page: Math.max(0, int(meta.page, 0)),
    itemsPerPage,
    totalPages: Math.max(0, int(meta.totalPages, 0)),
  };
}

function parseLinks(value: unknown): FeedLinks {
  const links = isRecord(value) ? value : {};
  return {
    self: str(links.self),
    next: typeof links.next === "string" ? links.next : null,
    prev: typeof links.prev === "string" ? links.prev : null,
  };
}

/**
 * Coerces an unknown value (a parsed JSON body) into a valid FeedResponse.
 * Always returns a well-formed object; unusable articles are filtered out.
 */
export function normalizeFeedResponse(raw: unknown): FeedResponse {
  const root = isRecord(raw) ? raw : {};
  const data = Array.isArray(root.data)
    ? root.data
        .map(parseArticle)
        .filter((article): article is Article => article !== null)
    : [];

  return {
    data,
    meta: parseMeta(root.meta, data.length),
    links: parseLinks(root.links),
  };
}

/**
 * Coerces an unknown value into an ArticleDetail, or null if it is not a valid
 * article. Reuses article validation and adds the body HTML string.
 */
export function normalizeArticleDetail(raw: unknown): ArticleDetail | null {
  const article = parseArticle(raw);
  if (article === null) {
    return null;
  }
  const body = isRecord(raw) && typeof raw.body === "string" ? raw.body : "";

  return { ...article, body };
}
