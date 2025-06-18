# Dockerfile
# Use the official PHP image with Apache
FROM php:8.1-apache

# Install necessary PHP extensions for MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache's rewrite module for clean URLs (optional but good practice)
RUN a2enmod rewrite

# Copy existing application files from your host to the container's web root
COPY . /var/www/html/

# Set proper permissions for web server
RUN chown -R www-data:www-data /var/www/html