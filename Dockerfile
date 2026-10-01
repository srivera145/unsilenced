# Dependencies. Composer installs PHPMailer and phpdotenv into vendor/ and
# publishes Deck's stylesheet, icon sprite and scripts into public_html/deck.
# No dev packages: the image runs the site, not the tests.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
COPY src/ src/
COPY public_html/ public_html/
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM php:8.2-apache

RUN a2enmod rewrite \
    && docker-php-ext-install pdo_mysql

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public_html

RUN sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# No client IP, Referer or User-Agent in Apache's logs (docs/DEPLOY-PRIVACY.md).
# The site file replaces the stock one, whose access log is "combined"; the
# other-vhosts log is switched off so no second access log is written.
COPY docker/apache/unsilenced-privacy.conf /etc/apache2/conf-available/unsilenced-privacy.conf
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
# A ServerName stops Apache logging the container's own address at startup
# ("Could not reliably determine the server's fully qualified domain name").
# ServerTokens Prod: the Server header says "Apache", not the Apache, OS and
# PHP versions.
RUN printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/unsilenced-server.conf \
    && a2enconf unsilenced-privacy unsilenced-server \
    && a2disconf other-vhosts-access-log

WORKDIR /var/www/html

# .dockerignore keeps .env, .git, tests, docs and local data out.
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=vendor /app/public_html/deck /var/www/html/public_html/deck

# Apache runs as www-data and writes app.log (and mail.log with
# MAIL_MAILER=log) under storage/. Phase 2: storage/vault holds encrypted
# evidence and storage/sessions the survivor pages' sessions; only www-data
# may read either. In production, mount the vault on a persistent volume
# (VAULT_PATH), or it is lost with the container.
RUN mkdir -p storage/logs storage/imports storage/vault storage/sessions \
    && chown -R www-data:www-data storage \
    && chmod 700 storage/vault storage/sessions

# Evidence uploads: 20 MB a file (config evidence.max_file_bytes), several at
# once. The sodium extension the vault needs is built into the official image.
RUN printf 'upload_max_filesize=20M\npost_max_size=64M\nmax_file_uploads=20\n' > /usr/local/etc/php/conf.d/unsilenced-uploads.ini
