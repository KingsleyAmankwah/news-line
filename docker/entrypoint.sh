#!/bin/sh
# Prepares the bind-mounted Drupal app on container start, then runs the CMD.
set -e

cd /var/www/html

# 1. Install PHP dependencies on first boot (vendor/ is not committed).
if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] Installing Composer dependencies..."
  composer install --no-dev --optimize-autoloader --no-interaction
fi

# 2. Point settings.php at the committed, env-driven production settings.
SETTINGS="web/sites/default/settings.php"
if [ ! -f "$SETTINGS" ] || ! grep -q "settings.prod.php" "$SETTINGS"; then
  echo "[entrypoint] Writing settings.php -> settings.prod.php include"
  echo "<?php" > "$SETTINGS"
  echo "require __DIR__ . '/settings.prod.php';" >> "$SETTINGS"
fi

# 3. Ensure writable directories exist and are owned by the FPM worker user.
mkdir -p web/sites/default/files keys ../private
chown -R www-data:www-data web/sites/default/files ../private
chmod -R 775 web/sites/default/files

exec "$@"
