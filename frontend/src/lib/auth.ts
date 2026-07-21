import "server-only";

/**
 * Server-only OAuth2 client-credentials token acquisition for the Drupal API.
 *
 * The token is cached in module scope and reused until shortly before it
 * expires, so the frontend performs at most one token exchange per expiry
 * window rather than one per feed request. Client credentials are read from
 * server environment variables and never reach the browser.
 */

interface TokenResponse {
  access_token?: string;
  expires_in?: number;
  token_type?: string;
}

let cachedToken: { value: string; expiresAt: number } | null = null;

/** Seconds subtracted from the token lifetime to avoid using a near-expired token. */
const EXPIRY_SKEW_SECONDS = 30;

function drupalBaseUrl(): string {
  return (process.env.DRUPAL_BASE_URL ?? "http://news-line.ddev.site:33000").replace(
    /\/+$/,
    "",
  );
}

export async function getAccessToken(): Promise<string> {
  if (cachedToken && Date.now() < cachedToken.expiresAt) {
    return cachedToken.value;
  }

  const clientId = process.env.OAUTH_CLIENT_ID;
  const clientSecret = process.env.OAUTH_CLIENT_SECRET;
  const scope = process.env.OAUTH_SCOPE ?? "article_feed:read";

  if (!clientId || !clientSecret) {
    throw new Error(
      "OAuth client credentials are not configured (OAUTH_CLIENT_ID / OAUTH_CLIENT_SECRET).",
    );
  }

  const response = await fetch(`${drupalBaseUrl()}/oauth/token`, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      grant_type: "client_credentials",
      client_id: clientId,
      client_secret: clientSecret,
      scope,
    }),
    // Never cache the token exchange itself.
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error(`OAuth token request failed with status ${response.status}.`);
  }

  const data = (await response.json()) as TokenResponse;
  if (!data.access_token) {
    throw new Error("OAuth token response did not contain an access token.");
  }

  const lifetime = Math.max(0, (data.expires_in ?? 0) - EXPIRY_SKEW_SECONDS);
  cachedToken = {
    value: data.access_token,
    expiresAt: Date.now() + lifetime * 1000,
  };

  return cachedToken.value;
}
