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
RUN a2enconf unsilenced-privacy \
    && a2disconf other-vhosts-access-log

WORKDIR /var/www/html

COPY . /var/www/html
