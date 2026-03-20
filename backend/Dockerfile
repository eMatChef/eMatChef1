FROM php:8.4-cli

# Timezone auf UTC setzen (konsistent mit Frontend ISO-Handling)
ENV TZ=UTC
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN echo "date.timezone = UTC" > /usr/local/etc/php/conf.d/timezone.ini

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get install symfony-cli

WORKDIR /var/www/html

# Copy composer files
COPY composer.json ./

# Install PHP dependencies (ohne lock file, da wir neu starten)
RUN composer install --no-interaction --prefer-dist --no-scripts

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 8081

CMD ["php", "-S", "0.0.0.0:8081", "-t", "public"]
