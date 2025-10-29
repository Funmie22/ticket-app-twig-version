# Use official PHP + Apache image
FROM php:8.1-apache

# Install dependencies
RUN apt-get update && apt-get install -y git unzip zip libzip-dev \
  && docker-php-ext-install pdo pdo_mysql zip \
  && rm -rf /var/lib/apt/lists/*

# Copy Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory inside the container
WORKDIR /var/www/html

# Copy project files into the container
COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 (Render uses this automatically)
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
