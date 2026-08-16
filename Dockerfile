FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2enmod rewrite

# Cho phép liệt kê thư mục và cấu hình đường dẫn frontend
ENV APACHE_DOCUMENT_ROOT /var/www/html/frontend
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/conf-available/*.conf

# Bật DirectoryIndex mở rộng cho các file php/html khác
RUN echo "DirectoryIndex index.php index.html login.php home.php main.php" >> /etc/apache2/apache2.conf

COPY . /var/www/html/
EXPOSE 80
