/**
 * Rewrites a backend-emitted asset URL onto the configured backend origin.
 *
 * Drupal emits image-style URLs whose host/port reflect however the request
 * reached it (behind a proxy/tunnel this is unreliable — sometimes "localhost",
 * sometimes carrying an internal port like :33000). The frontend owns the
 * public backend location, so it forces the origin here.
 *
 * hostname and port are set SEPARATELY on purpose: the URL `host` setter does
 * not clear an existing port when the replacement value has none, so a stray
 * backend port would leak through. Setting `port` explicitly to the base's port
 * (empty for standard 80/443) guarantees the exact intended origin.
 */
export function toBackendUrl(rawUrl: string | null, baseUrl: string): string | null {
  if (!rawUrl) {
    return rawUrl;
  }
  try {
    const base = new URL(baseUrl);
    const resolved = new URL(rawUrl, base);
    resolved.protocol = base.protocol;
    resolved.hostname = base.hostname;
    resolved.port = base.port;
    return resolved.toString();
  } catch {
    return rawUrl;
  }
}
