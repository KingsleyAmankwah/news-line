# Production PHP-FPM image for the Drupal backend.
# Code is bind-mounted from the git checkout (see compose.prod.yaml), so this
# image only provides the PHP runtime, extensions, Composer, and the entrypoint.
FROM php:8.3-fpm

# install-php-extensions resolves system deps automatically.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions gd pdo_mysql opcache intl zip mbstring

# Composer (used on first boot to install dependencies).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Production PHP settings (opcache, limits).
COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
