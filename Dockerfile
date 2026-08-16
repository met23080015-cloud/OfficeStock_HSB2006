FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2enmod rewrite

# Cho phép tự động xem danh sách file trong thư mục
RUN sed -i 's/Options Indexes FollowSymLinks/Options Indexes FollowSymLinks ExecCGI/' /etc/apache2/apache2.conf
RUN echo "<Directory /var/www/html>\n  Options +Indexes +FollowSymLinks\n  AllowOverride All\n  Require all granted\n</Directory>" >> /etc/apache2/apache2.conf

COPY . /var/www/html/
EXPOSE 80
