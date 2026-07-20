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

# 3. (Auth milestone) Generate the OAuth2 key pair — never commit these.
#    Detailed steps are added in the Auth milestone.

# 4. Open the site.
ddev launch
```

Frontend setup instructions are added in the frontend milestone.

## Coding standards & tests

```bash
# PHP CodeSniffer (Drupal + DrupalPractice rulesets).
ddev exec vendor/bin/phpcs

# PHPUnit (Unit + Kernel tests for custom code).
ddev exec vendor/bin/phpunit -c web/core web/modules/custom
```

## Milestones

- [x] **M1 — Scaffold**: Drupal 11 + DDEV, contrib deps, dev tooling, PHPCS, repo hygiene.
- [x] **M2 — Content model** (`newsline_core`): Article type, fields, Media, image styles, taxonomy, and pathauto pattern shipped as installable `config/install`.
- [ ] **Deployment pipeline**: Oracle (Drupal) + Vercel (frontend), prod settings, CI/CD. _(Ordering TBD with client.)_
- [ ] **Custom REST Resource + shaping service + tests** (`newsline_api`).
- [ ] **OAuth2 auth** (`simple_oauth`).
- [ ] **Next.js frontend + design system + ISR**.
- [ ] **On-demand revalidation + docs finalize**.

## Skills demonstrated

_Finalized in the last milestone — CV-ready bullets are drafted at the end of
each milestone and collected here._

## License

GPL-2.0-or-later (inherits Drupal's license).
