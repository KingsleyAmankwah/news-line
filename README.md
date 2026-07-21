# News Line — Decoupled Drupal 11 + Next.js

A portfolio project demonstrating a **decoupled (headless) Drupal 11 backend**
serving a **custom, purpose-shaped REST API** to a **Next.js frontend** that
renders with Incremental Static Regeneration (ISR).

The point of the project is the *seam between the two systems*: rather than
exposing raw JSON:API entity dumps, the backend ships a hand-designed REST
Resource plugin that returns a flattened, renamed, frontend-friendly JSON
contract with fully resolved media URLs.

> Status: **Milestone 1 — scaffold complete.** See the milestone checklist below.

---

## What this project demonstrates

- Designing an explicit **API contract** and implementing it as a custom Drupal
  **REST Resource plugin** (`ResourceInterface`), not auto-generated output.
- Clean Drupal 11 architecture: PHP 8.3+ attributes, typed properties,
  constructor dependency injection, thin plugins delegating to testable
  services.
- **OAuth2** machine-to-machine auth (`simple_oauth`, client credentials grant).
- Proper **render cache metadata** (tags / contexts / max-age) on API output.
- **PHPUnit** coverage (Unit + Kernel) for the response-shaping logic.
- A small, token-driven **component design system** in Next.js (CSS Modules,
  logical properties, RTL-safe).
- **ISR** with optional on-demand revalidation triggered from Drupal on content
  changes.

## Architecture

```
                    OAuth2 (client_credentials)
  ┌──────────────┐  Bearer token        ┌───────────────────────────┐
  │  Next.js app │ ───────────────────▶ │  Drupal 11 (headless CMS)  │
  │  (frontend/) │                       │                            │
  │              │  GET /api/article-    │  newsline_api              │
  │  - ISR       │  feed  (custom JSON)  │   └ ArticleFeedResource    │
  │  - design    │ ◀─────────────────── │       (REST Resource plugin)│
  │    system    │   flattened contract  │   └ ArticleFeedNormalizer  │
  └──────────────┘                       │       (shaping service)    │
                                         │  newsline_core             │
                                         │   └ content model (config) │
                                         └───────────────────────────┘
```

- **`newsline_core`** — owns the content model (Article content type, fields,
  Media type, image styles, taxonomy) as installable configuration.
- **`newsline_api`** — owns delivery: the REST Resource plugin, the shaping
  service, cache metadata, and OAuth scope wiring. Depends on `newsline_core`.
- **`frontend/`** — Next.js (App Router) consuming the feed with ISR.

## Tech stack

| Layer      | Choice                                                    |
|------------|-----------------------------------------------------------|
| CMS        | Drupal 11.4, PHP 8.3+ (8.4 in the DDEV container)         |
| API        | Core `rest` + custom `ArticleFeedResource` plugin         |
| Auth       | `simple_oauth` (OAuth2 client credentials)                |
| Slugs      | `pathauto`                                                 |
| Frontend   | Next.js (App Router), CSS Modules + design tokens         |
| Local dev  | DDEV (Docker)                                              |

## Local development setup (DDEV)

> Prerequisites: [Docker](https://www.docker.com/) and
> [DDEV](https://ddev.readthedocs.io/) installed.

```bash
# 1. Start the containers and install PHP dependencies.
ddev start
ddev composer install

# 2. Install the site (or import an existing DB).
ddev drush site:install --yes

# 3. Enable the custom modules (imports the content model + API config).
ddev drush en newsline_core newsline_api -y

# 4. Set up OAuth2 (see "Authentication" below for the full flow).
ddev drush so:generate-keys /var/www/html/keys
ddev drush cset simple_oauth.settings public_key /var/www/html/keys/public.key -y
ddev drush cset simple_oauth.settings private_key /var/www/html/keys/private.key -y
ddev drush cset simple_oauth.settings scope_provider dynamic -y

# 5. Open the site.
ddev launch
```

> Note: this machine's DDEV runs on mapped ports (XAMPP owns 80/443), so the
> local site is at `http://news-line.ddev.site:33000`.

## Frontend (Next.js)

The frontend lives in `frontend/` and runs on the host (Node 20+), separate
from the DDEV backend stack:

```bash
cd frontend
cp .env.example .env.local     # set OAUTH_CLIENT_ID / OAUTH_CLIENT_SECRET
npm install
npm run dev                    # http://localhost:3000
```

It acquires an OAuth token and fetches the feed **server-side** (credentials
never reach the browser), renders the article grid with **ISR**, and shows an
unavailable state if the backend is down. Other scripts:

- `npm run test` — Vitest (defensive feed-parser unit tests)
- `npm run lint` — ESLint
- `npm run build` — production build

## Authentication (OAuth2)

The feed is protected with `simple_oauth` using the **client-credentials**
grant — the right fit for machine-to-machine access, since the Next.js server
(not the browser) fetches the feed during ISR.

Configuration that ships in the repo (module `config/install`):

- `rest.resource.article_feed` — the endpoint, authenticated via `oauth2`.
- `simple_oauth.oauth2_scope.article_feed_read` — an OAuth2 scope named
  `article_feed:read` that grants the `restful get article_feed` permission.

Per-environment setup (secrets/keys, **never committed**):

1. Generate the key pair and point `simple_oauth` at it (step 4 above). Keys
   live outside the docroot in `keys/` and are gitignored.
2. Create a confidential Consumer for the frontend and assign it a dedicated
   service user plus the scope:

   ```bash
   # Create a low-privilege service account the token acts as.
   ddev drush user:create newsline_service

   # Create the client via the UI at /admin/config/services/consumer/add,
   # or with drush; set grant type = Client Credentials, scope =
   # "article_feed:read", User = newsline_service, and record the secret.
   ```

Token flow the frontend uses:

```bash
# 1. Exchange client credentials for an access token.
curl -X POST http://news-line.ddev.site:33000/oauth/token \
  -d grant_type=client_credentials \
  -d client_id=<client-id> \
  -d client_secret=<secret> \
  -d scope=article_feed:read

# 2. Call the feed with the bearer token.
curl -H 'Authorization: Bearer <access-token>' \
  'http://news-line.ddev.site:33000/api/article-feed?_format=json'
```

Without a valid token the endpoint returns `403`.

## Coding standards & tests

```bash
# PHP CodeSniffer (Drupal + DrupalPractice rulesets).
ddev exec vendor/bin/phpcs

# PHPStan static analysis (level 5) of custom code.
ddev exec vendor/bin/phpstan analyse -c phpstan.neon.dist

# PHPUnit (Unit + Kernel tests for custom code).
ddev exec 'SIMPLETEST_DB="mysql://db:db@db/db" vendor/bin/phpunit -c web/core web/modules/custom'
```

## Milestones

- [x] **M1 — Scaffold**: Drupal 11 + DDEV, contrib deps, dev tooling, PHPCS, repo hygiene.
- [x] **M2 — Content model** (`newsline_core`): Article type, fields, Media, image styles, taxonomy, and pathauto pattern shipped as installable `config/install`.
- [x] **M3 — Custom REST Resource + shaping service + tests** (`newsline_api`): `ArticleFeedResource` (ResourceInterface) serving a flattened, cache-aware feed via a testable normalizer; PHPUnit unit + kernel coverage; PHPStan level 5.
- [x] **M4 — OAuth2 auth** (`simple_oauth`): feed protected via client-credentials grant; scope maps to the feed permission; keys/consumer/settings are per-environment.
- [x] **M5 — Next.js frontend + design system + ISR**: Tailwind design-token system; `Button`/`Card`/`Layout`; server-side OAuth token caching; defensive feed parsing (Vitest); statically-generated feed page with ISR.
- [ ] **M6 — On-demand revalidation**.
- [ ] **M7 — Deployment**: Oracle (Docker on Compute VM) + Vercel, full config sync, GitHub Actions CI/CD. _(Last — deploys the complete stack.)_

## Skills demonstrated

_Finalized in the last milestone — CV-ready bullets are drafted at the end of
each milestone and collected here._

## License

GPL-2.0-or-later (inherits Drupal's license).
