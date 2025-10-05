# Dockerfile für die PHP-App
FROM php:8.2-apache

# Aktiviere PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Aktiviere Apache Rewrite Modul
RUN a2enmod rewrite

# Setze Document Root auf public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Erlaube .htaccess Override
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Kopiere den Code in den Container
COPY . /var/www/html/

# Setze Arbeitsverzeichnis
WORKDIR /var/www/html

# Exponiere Port 80
EXPOSE 80