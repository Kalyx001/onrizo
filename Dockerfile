# Use official PHP + Apache image
FROM php:8.2-apache

# Copy all your code into the container
COPY . /var/www/html/

# Install MySQL support for PHP
RUN docker-php-ext-install mysqli

# Expose port 80
EXPOSE 80
