<?php

/**
 * @file
 * Production settings, driven entirely by environment variables.
 *
 * This file is committed and contains NO secrets — every sensitive or
 * environment-specific value is read from the environment (see .env.example).
 * The container entrypoint generates a settings.php that includes this file.
 */

// phpcs:ignoreFile

$databases['default']['default'] = [
  'driver' => 'mysql',
  'host' => getenv('DB_HOST') ?: 'db',
  'port' => getenv('DB_PORT') ?: '3306',
  'database' => getenv('DB_NAME') ?: 'drupal',
  'username' => getenv('DB_USER') ?: 'drupal',
  'password' => getenv('DB_PASSWORD') ?: '',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: '';
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = '../private';
$settings['update_free_access'] = FALSE;

// Behind the Caddy reverse proxy (TLS terminates there).
$settings['reverse_proxy'] = TRUE;
if (!empty($_SERVER['REMOTE_ADDR'])) {
  $settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR']];
}

// Only accept requests for the configured public domain.
$domain = getenv('SITE_DOMAIN') ?: 'localhost';
$settings['trusted_host_patterns'] = ['^' . str_replace('.', '\\.', $domain) . '$'];

// OAuth2 key material (generated on the server, mounted at keys/).
$config['simple_oauth.settings']['public_key'] = getenv('OAUTH_PUBLIC_KEY') ?: '/var/www/html/keys/public.key';
$config['simple_oauth.settings']['private_key'] = getenv('OAUTH_PRIVATE_KEY') ?: '/var/www/html/keys/private.key';
$config['simple_oauth.settings']['scope_provider'] = 'dynamic';

// On-demand revalidation of the Vercel frontend (endpoint + shared secret).
$config['newsline_api.settings']['revalidation_endpoint'] = getenv('REVALIDATE_ENDPOINT') ?: '';
$config['newsline_api.settings']['revalidation_secret'] = getenv('REVALIDATE_SECRET') ?: '';
