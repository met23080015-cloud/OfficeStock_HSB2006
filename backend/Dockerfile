FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

COPY . /var/www/html
RUN chmod +x /var/www/html/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

EXPOSE 10000
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
