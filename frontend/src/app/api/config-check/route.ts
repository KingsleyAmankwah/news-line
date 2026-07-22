import { NextResponse } from "next/server";

// TEMPORARY debug route — reports the effective runtime config so we can
// confirm what Vercel is actually injecting. Reads env at request time (never
// cached). Exposes no secret values, only whether they are set. Remove after use.
export const dynamic = "force-dynamic";

export async function GET() {
  return NextResponse.json({
    drupalBaseUrl: process.env.DRUPAL_BASE_URL ?? null,
    oauthScope: process.env.OAUTH_SCOPE ?? null,
    feedRevalidateSeconds: process.env.FEED_REVALIDATE_SECONDS ?? null,
    hasClientId: Boolean(process.env.OAUTH_CLIENT_ID),
    hasClientSecret: Boolean(process.env.OAUTH_CLIENT_SECRET),
    hasRevalidateSecret: Boolean(process.env.REVALIDATE_SECRET),
  });
}
