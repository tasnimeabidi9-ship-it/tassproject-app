FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev curl \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install PHP dependencies first (cached layer)
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy backend code
COPY backend/ ./

# Copy frontend source
COPY frontend/ ./frontend-build/

# Build script
COPY render-build.sh /render-build.sh
RUN chmod +x /render-build.sh

EXPOSE 8000

CMD ["/render-build.sh"]
