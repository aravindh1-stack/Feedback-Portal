# Use the official PHP 8.1 with Apache image
FROM php:8.1-apache

# Copy all project files into the Apache web directory
COPY . /var/www/html/

# Enable Apache's mod_rewrite (for clean URLs, good to have)
RUN a2enmod rewrite

# Set correct permissions for Apache
RUN chown -R www-data:www-data /var/www/html
