/**
 * TypeScript representation of the Article Feed API contract served by the
 * Drupal `article_feed` REST resource. These mirror the shape produced by
 * ArticleFeedNormalizer on the backend.
 */

export interface TermRef {
  id: string;
  name: string;
  slug: string;
}

export interface AuthorRef {
  id: string;
  name: string;
}

export interface HeroImage {
  alt: string;
  width: number | null;
  height: number | null;
  src: string;
  thumbnail: string | null;
}

export interface Article {
  id: string;
  type: "article";
  title: string;
  path: string;
  summary: string;
  promoted: boolean;
  sticky: boolean;
  readingTimeMinutes: number;
  publishedAt: string;
  updatedAt: string;
  author: AuthorRef | null;
  category: TermRef | null;
  tags: TermRef[];
  hero: HeroImage | null;
}

export interface FeedMeta {
  count: number;
  page: number;
  itemsPerPage: number;
  totalPages: number;
}

export interface FeedLinks {
  self: string;
  next: string | null;
  prev: string | null;
}

export interface FeedResponse {
  data: Article[];
  meta: FeedMeta;
  links: FeedLinks;
}

export interface FeedQuery {
  page?: number;
  itemsPerPage?: number;
  category?: string;
}
