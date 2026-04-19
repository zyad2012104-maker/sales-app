FROM php:8.4-apache

# تثبيت الملحقات المطلوبة
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    libonig-dev

# تثبيت ملحقات PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd pdo_mysql mbstring exif pcntl bcmath

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# نسخ ملفات المشروع
COPY . /var/www/html/

# تثبيت الحزم مع تجاهل متطلبات PHP
RUN composer install --no-dev --optimize-autoloader --ignore-platform-req=php

# إعداد الصلاحيات
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# تمكين Apache mod_rewrite
RUN a2enmod rewrite

# تشغيل الترحيلات عند بدء التشغيل
CMD php artisan migrate --force && apache2-foreground