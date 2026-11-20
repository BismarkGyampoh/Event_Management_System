FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev \
    && docker-php-ext-install mbstring pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\n\n' > /usr/local/etc/php/conf.d/production.ini

RUN printf '<Directory /var/www/html/admin/backups>\n    Require all denied\n</Directory>\n' > /etc/apache2/conf-available/protect-backups.conf \
    && a2enconf protect-backups

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p /var/www/html/admin/backups \
    && chown -R www-data:www-data /var/www/html/admin/backups

CMD ["sh", "-c", "port=${PORT:-10000}; sed -i \"s/Listen 80/Listen ${port}/\" /etc/apache2/ports.conf; sed -i \"s/:80>/:${port}>/\" /etc/apache2/sites-available/000-default.conf; exec apache2-foreground"]
