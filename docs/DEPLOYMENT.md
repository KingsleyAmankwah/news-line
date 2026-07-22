# Deployment — Drupal backend on an Oracle Cloud VM

The backend runs as a Docker Compose stack: **Caddy** (auto-HTTPS) → **PHP-FPM**
(the Drupal app) → **MariaDB**. Deploy = `git pull` + `docker compose up`.

> All artifacts are committed: `Dockerfile`, `compose.prod.yaml`, `docker/`,
> `web/sites/default/settings.prod.php`, `.env.example`. Secrets live only in the
> VM's untracked `.env` and generated `keys/`.

## Prerequisites

- An OCI Compute instance (Ubuntu 22.04+; the Always-Free Ampere shape is fine).
- A domain/subdomain you can point at the VM (e.g. `api.yourdomain.com`).
- SSH access to the instance.

## 1. Open the firewall (OCI)

Two layers must allow 80/443:

1. **OCI Security List / NSG** (console): add ingress rules for TCP **80** and
   **443** from `0.0.0.0/0`.
2. **On the VM** (Oracle Ubuntu images ship restrictive iptables):
   ```bash
   sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
   sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
   sudo netfilter-persistent save
   ```

## 2. Public hostname (no domain required)

Caddy needs a resolvable hostname to issue a TLS certificate. Pick one:

- **Own a domain** → add an **A record** → the VM IP, and set
  `SITE_DOMAIN=api.yourdomain.com`. Confirm with `dig +short api.yourdomain.com`.
- **No domain → use `nip.io` (recommended).** It maps your IP into a hostname
  with no signup: for IP `203.0.113.5`, set
  `SITE_DOMAIN=203-0-113-5.nip.io`. Caddy still gets a real Let's Encrypt cert,
  so you keep HTTPS with no other change.
- **No domain, HTTP only.** Set `SITE_DOMAIN=<vm-ip>` and change the first line
  of `docker/Caddyfile` from `{$SITE_DOMAIN} {` to `http://{$SITE_DOMAIN} {`
  (serves plain HTTP, no cert). The frontend still works — Vercel calls the
  backend server-side and proxies images — but admin login is unencrypted.

## 3. Install Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # log out/in afterwards
```

## 4. Clone and configure

```bash
git clone <your-repo-url> newsline && cd newsline
cp .env.example .env
# Edit .env: set SITE_DOMAIN, DB_* passwords, and a hash salt:
#   DRUPAL_HASH_SALT=$(openssl rand -hex 32)
mkdir -p keys private
```

## 5. Start the stack

```bash
docker compose -f compose.prod.yaml up -d --build
```

Caddy will obtain a Let's Encrypt certificate for `SITE_DOMAIN` automatically on
first request. Check logs: `docker compose -f compose.prod.yaml logs -f caddy`.

## 6. Load the site

Fastest reliable path for a demo — mirror your local database and files.

**On your local machine:**
```bash
ddev export-db --gzip --file=newsline.sql.gz
tar czf files.tgz -C web/sites/default files
scp newsline.sql.gz files.tgz ubuntu@<vm-ip>:~/newsline/
```

**On the VM:**
```bash
# Import the database.
zcat newsline.sql.gz | docker compose -f compose.prod.yaml exec -T db \
  mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME"

# Restore uploaded files (hero images).
tar xzf files.tgz -C /tmp && \
  docker compose -f compose.prod.yaml cp /tmp/files/. php:/var/www/html/web/sites/default/files/

docker compose -f compose.prod.yaml exec php vendor/bin/drush cr
```

> Alternative (clean slate, no content): install fresh, then enable the modules
> and seed. Requires the `standard` profile's text formats, so remove the
> profile's colliding `article`/`tags`/`image` config before enabling
> `newsline_core`. The DB import above avoids this entirely.

## 7. OAuth keys + a client

```bash
# Generate the signing key pair (mounted from ./keys).
docker compose -f compose.prod.yaml exec php vendor/bin/drush so:generate-keys /var/www/html/keys

# If you imported the DB, the Consumer already exists. Otherwise create one:
#   /admin/config/services/consumer/add  (grant: Client Credentials,
#   scopes: article_feed:read + article:read, User: a low-privilege service user)
```

Verify end to end:
```bash
curl -sS -X POST https://api.yourdomain.com/oauth/token \
  -d grant_type=client_credentials -d client_id=<id> -d client_secret=<secret> \
  --data-urlencode 'scope=article_feed:read article:read'

curl -sS -H 'Authorization: Bearer <token>' \
  'https://api.yourdomain.com/api/article-feed?_format=json'
```

## 8. Point the frontend at it

In the Vercel project's environment variables set:
- `DRUPAL_BASE_URL=https://api.yourdomain.com`
- `OAUTH_CLIENT_ID`, `OAUTH_CLIENT_SECRET`, `OAUTH_SCOPE=article_feed:read article:read`
- `REVALIDATE_SECRET` (also set `REVALIDATE_ENDPOINT` in the VM's `.env` to the
  Vercel `/api/revalidate` URL so publishes refresh the site instantly).

## Redeploy

```bash
git pull
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml exec php vendor/bin/drush deploy   # updb + cim + cr
```
