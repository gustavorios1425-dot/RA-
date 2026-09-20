FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql \
 && a2enmod headers rewrite
COPY src/ /var/www/html/
