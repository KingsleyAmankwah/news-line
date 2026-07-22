import { revalidateTag } from "next/cache";
import { NextResponse } from "next/server";

/**
 * On-demand revalidation webhook.
 *
 * Drupal calls this endpoint when an article is created, updated, or deleted,
 * so the feed and article pages refresh immediately instead of waiting for the
 * ISR window. The request must carry the shared secret; the "article-feed" tag
 * is attached to both the feed and every detail page, so one call refreshes
 * all cached article content.
 */
export async function POST(request: Request): Promise<NextResponse> {
  const secret = process.env.REVALIDATE_SECRET;
  const provided = request.headers.get("x-revalidate-secret");

  if (!secret || provided !== secret) {
    return NextResponse.json({ revalidated: false }, { status: 401 });
  }

  // Next 16 requires a cache-life profile; the call purges the tag on demand
  // and the freshly cached entry then lives for the feed's revalidate window.
  revalidateTag("article-feed", { expire: 60 });

  return NextResponse.json({ revalidated: true, tag: "article-feed" });
}
