# Multi-stage build for production deployment
# Stage 1: Build React POS application
FROM node:20-alpine AS pos-builder

WORKDIR /app
COPY pos/package*.json ./
RUN npm ci

COPY pos/ ./
RUN DOCKER_BUILD=true npm run build

# Stage 2: PHP Apache server with built POS app
FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Install system dependencies and PHP extensions
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
  && docker-php-ext-install pdo pdo_pgsql zip \
  && docker-php-ext-enable pdo_pgsql \
  && (a2dismod mpm_event mpm_worker || true) \
  && a2enmod mpm_prefork rewrite \
  && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf \
  && printf "<Directory \"${APACHE_DOCUMENT_ROOT}\">\n    AllowOverride All\n    Require all granted\n</Directory>\n" > /etc/apache2/conf-enabled/app.conf \
  && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Copy built POS app from builder stage
COPY --from=pos-builder --chown=www-data:www-data /app/dist /var/www/html/public/pos

# Set proper permissions and normalize line endings for Linux
RUN chown -R www-data:www-data /var/www/html/public/uploads \
  && chmod -R 755 /var/www/html/public/uploads \
  && sed -i 's/\r$//' /var/www/html/docker-entrypoint.sh \
  && chmod +x /var/www/html/docker-entrypoint.sh

# Expose standard web and container ports
EXPOSE 80 8080

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
