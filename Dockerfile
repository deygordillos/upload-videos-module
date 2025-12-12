# Dockerfile para Video Upload API
# PHP 8.3.16 con Apache

FROM php:8.3.16-apache

# Metadata
LABEL maintainer="SimpleData Corp"
LABEL version="1.0.0"
LABEL description="Secure Video Upload API"

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    g++ \
    git \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-install \
    mysqli \
    intl \
    opcache \
    pdo \
    pdo_mysql \
    zip \
    gd \
    && pecl install apcu \
    && docker-php-ext-enable apcu

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configurar PHP (límites de upload)
RUN echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Configurar Apache
RUN a2enmod rewrite headers
COPY ./docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Crear usuario no-root
RUN useradd -m -u 1000 -s /bin/bash appuser

# Configurar directorio de trabajo
WORKDIR /api

# Copiar archivos del proyecto
COPY --chown=appuser:appuser . /api/

# Instalar dependencias de PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Crear directorios necesarios y establecer permisos
RUN mkdir -p uploads log \
    && chown -R appuser:www-data uploads log \
    && chmod -R 775 uploads log

# Configurar permisos
RUN chown -R appuser:www-data /api \
    && chmod -R 755 /api \
    && chmod 644 core.php

# Exponer puerto
EXPOSE 80

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
  CMD curl -f http://localhost/v1/videos/health || exit 1

# Comando de inicio
CMD ["apache2-foreground"]