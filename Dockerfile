FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Bật module rewrite cho Apache
RUN a2enmod rewrite

# Chuyển đường dẫn gốc Apache trỏ thẳng vào thư mục frontend
ENV APACHE_DOCUMENT_ROOT /var/www/html/frontend
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/conf-available/*.conf

COPY . /var/www/html/
EXPOSE 80
