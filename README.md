# News Line — Decoupled Drupal 11 + Next.js

A **decoupled (headless) Drupal 11 backend** serving a
**custom, purpose-shaped REST API** to a **React 18+/Next.js frontend** rendered
with Incremental Static Regeneration.

The point of the project is the _seam between the two systems_. Instead of
exposing raw JSON:API entity dumps, the backend ships a hand-designed REST
Resource plugin that returns a flattened, renamed, frontend-friendly JSON
contract with fully resolved media URLs; the frontend consumes it with modern
React patterns and a token-driven design system that round-trips to Figma.

---

## What this project demonstrates

**Drupal / API**

- An explicit **API contract** implemented as a custom **REST Resource plugin**
  (`ResourceInterface`, PHP 8 attribute) — a feed endpoint and a single-article
  endpoint — not auto-generated output.
- Clean Drupal 11 architecture: attributes over annotations, typed properties,
  constructor DI, thin plugins delegating to a testable normalizer service.
- **OAuth2** machine-to-machine auth (`simple_oauth`, client-credentials) with
  least-privilege scopes.
- Proper **render cache metadata** (tags/contexts) and **on-demand
  revalidation** of the frontend when content changes.
- **PHPUnit** (Unit + Kernel) + **PHPStan** (level 5) + **PHPCS** (Drupal).

**React 18+ / Next.js**

- Async **Server Components**, **`<Suspense>`** streaming with a skeleton
  fallback, route-level **`loading`** UI, and an **error boundary** (`error.tsx`).
- A **client component** (`ArticleExplorer`) with `useState`/`useMemo` for
  interactive category filtering, composed from the shared design-system
  components.
- **ISR** for the feed and **SSG** (`generateStaticParams`) for article pages,
  with server-side OAuth token caching so credentials never reach the browser.

**Design system + Figma (design-to-dev)**

- A token-driven **Tailwind v4** design system (`Button`, `Card`, `Layout`)
  with light/dark theming and RTL-safe logical utilities.
- A matching **Figma design system generated from the code** — variables
  (scoped, with `var(--…)` code syntax), a `Button` variant set, and a `Card` —
  demonstrating the code↔design round-trip: **[Figma file](https://www.figma.com/design/LgbjCqTF5bneG1lYqWMaPq)**.

**Testing**

- **Jest + React Testing Library** for the frontend components (Button, Card,
  the feed explorer) plus the defensive response parser.

---

## Architecture

```
                      OAuth2 (client_credentials)
  ┌───────────────┐   Bearer token          ┌────────────────────────────┐
  │  Next.js app  │ ─────────────────────▶  │  Drupal 11 (headless CMS)   │
  │  (frontend/)  │   GET /api/article-feed │                             │
  │               │   GET /api/article/{s}  │  newsline_api               │
  │  Server Comp. │ ◀─────────────────────  │   ├ ArticleFeedResource      │
  │  Suspense/ISR │   flattened JSON        │   ├ ArticleResource (detail) │
  │  design system│                         │   ├ ArticleFeedNormalizer    │
  │               │   POST /api/revalidate  │   └ RevalidationNotifier ────┼─┐
  │  (revalidate) │ ◀───────────────────────┼─ hook_node_{ins,upd,del} ────┘ │
  └───────────────┘   on content change     │  newsline_core (content model) │
                                             └────────────────────────────────┘
```

- **`newsline_core`** — the content model (Article type, fields, Media, image
  styles, taxonomy, pathauto pattern) as installable configuration.
- **`newsline_api`** — delivery: the two REST Resource plugins, the shaping
  service, OAuth scopes, cache metadata, and the revalidation notifier.
- **`frontend/`** — Next.js (App Router) consuming the API with React 18+
  patterns and ISR.

## Tech stack

| Layer     | Choice                                                         |
| --------- | -------------------------------------------------------------- |
| CMS       | Drupal 11.4, PHP 8.3+ (8.4 in the DDEV container)              |
| API       | Core `rest` + custom `ArticleFeedResource` / `ArticleResource` |
| Auth      | `simple_oauth` (OAuth2 client credentials)                     |
| Slugs     | `pathauto`                                                     |
| Frontend  | Next.js 16 (App Router), React 19, TypeScript, Tailwind v4     |
| Design    | Design tokens → Tailwind + Figma design system                 |
| Testing   | PHPUnit, PHPStan, PHPCS · Jest + React Testing Library         |
| Local dev | DDEV (Docker) for Drupal; host Node for the frontend           |

## Local development setup (DDEV)

> Prerequisites: [Docker](https://www.docker.com/) and
> [DDEV](https://ddev.readthedocs.io/).

```bash
# 1. Start containers + install PHP dependencies.
ddev start
ddev composer install

# 2. Install the site.
ddev drush site:install --yes

# 3. Enable the custom modules (imports the content model + API config).
ddev drush en newsline_core newsline_api -y

# 4. Set up OAuth2 (see "Authentication" below).
ddev drush so:generate-keys /var/www/html/keys
ddev drush cset simple_oauth.settings public_key /var/www/html/keys/public.key -y
ddev drush cset simple_oauth.settings private_key /var/www/html/keys/private.key -y
ddev drush cset simple_oauth.settings scope_provider dynamic -y

# 5. Open the site.
ddev launch
```

> Note: on the author's machine DDEV runs on mapped ports (XAMPP owns 80/443),
> so the local site is `http://news-line.ddev.site:33000`.

## Frontend (Next.js)

Runs on the host (Node 20+), separate from the DDEV backend:

```bash
cd frontend
cp .env.example .env.local     # set the OAuth client id/secret + revalidate secret
npm install
npm run dev                    # http://localhost:3000
```

The frontend acquires an OAuth token and fetches the API **server-side**
(credentials never reach the browser), renders with **ISR** (feed) and **SSG**
(article pages), streams the feed behind a `<Suspense>` skeleton, and degrades
to an error boundary if the backend is down. Scripts:

- `npm run test` — **Jest + React Testing Library**
- `npm run lint` — ESLint
- `npm run build` — production build

## Authentication (OAuth2)

The API is protected with `simple_oauth` using the **client-credentials** grant
— the right fit for machine-to-machine access, since the Next.js server (not the
browser) calls the API.

Configuration shipped in the repo (`config/install`):

- `rest.resource.article_feed` / `rest.resource.article` — the endpoints,
  authenticated via `oauth2`.
- `simple_oauth.oauth2_scope.article_feed_read` (`article_feed:read`) and
  `simple_oauth.oauth2_scope.article_read` (`article:read`) — least-privilege
  scopes mapping to the two `restful get …` permissions.

Per-environment (secrets/keys, **never committed**): generate the key pair
(step 4), create a confidential Consumer with a dedicated service user and both
scopes, and set the revalidation endpoint/secret via `settings.php` overrides.

```bash
# Token exchange the frontend performs:
curl -X POST http://news-line.ddev.site:33000/oauth/token \
  -d grant_type=client_credentials -d client_id=<id> -d client_secret=<secret> \
  --data-urlencode 'scope=article_feed:read article:read'

# Call the API with the bearer token:
curl -H 'Authorization: Bearer <token>' \
  'http://news-line.ddev.site:33000/api/article-feed?_format=json'
```

Without a valid token the endpoints return `403`.

## On-demand revalidation

When an article is created/updated/deleted, `hook_node_*` calls
`RevalidationNotifier`, which POSTs to the frontend's `/api/revalidate` webhook
(shared secret). The route calls `revalidateTag('article-feed')`, so edits go
live immediately instead of waiting out the ISR window. The endpoint/secret are
per-environment settings and fail safe (logged, never blocking a save).

## Testing

```bash
# --- Drupal ---
ddev exec vendor/bin/phpcs
ddev exec vendor/bin/phpstan analyse -c phpstan.neon.dist
ddev exec 'SIMPLETEST_DB="mysql://db:db@db/db" vendor/bin/phpunit -c web/core web/modules/custom'

# --- Frontend ---
cd frontend && npm run test    # Jest + React Testing Library
```

Frontend tests cover the `Button` (variants, type default, click/disabled
behavior), the `Card` (link target, metadata, hero alt, graceful no-image), the
`ArticleExplorer` (renders + filters by category, `aria-pressed` state), and the
defensive feed parser.

## Status

- [x] Article Feed **content model** (`newsline_core`, config-managed)
- [x] Custom **REST Resource** plugins — feed + single article (flattened JSON,
      resolved media, cache metadata), with PHPUnit/PHPStan
- [x] **OAuth2** client-credentials auth with least-privilege scopes
- [x] **React 18+ frontend** — Server Components, Suspense/loading, error
      boundary, hooks-driven filtering, ISR + SSG
- [x] **Design system** in Tailwind + a **Figma** system generated from code
- [x] **Jest + RTL** component tests
- [x] **On-demand revalidation** webhook
- [ ] **Demo deploy** — Vercel (frontend) + Oracle Cloud VM / DDEV host (backend)

> Deployment intentionally targets the fastest reliable demo link (Vercel +
> Oracle/DDEV) rather than a bespoke AWS pipeline.

## Skills demonstrated

- **JavaScript / React 18+** — async Server Components, `<Suspense>` streaming
  with skeletons, error boundaries, client components with `useState`/`useMemo`,
  server/client composition, and ISR + `generateStaticParams` (SSG).
- **Figma (design-to-dev)** — generated a token-driven Figma design system from
  the codebase: variables with scopes and `var(--…)` code syntax, a `Button`
  variant set, and a `Card`, all bound to tokens — the code↔design round-trip.
- **Jest + React Testing Library** — behavior- and accessibility-focused
  component tests (roles, `aria-pressed`, click/disabled semantics, graceful
  states) rather than snapshot noise.
- **Headless Drupal** — a custom REST Resource plugin shaping a deliberate API
  contract, a config-managed content model, OAuth2 with scopes, render cache
  metadata, on-demand revalidation, and PHPUnit/PHPStan coverage.

## License

GPL-2.0-or-later (inherits Drupal's license).
