# Immagine di PRODUZIONE: il codice è copiato dentro l'immagine (niente cartelle montate).
# Build dalla cartella del progetto:  docker build -f docker/prod/Dockerfile -t salvareel .
# config.php NON è nell'immagine: va montato in /var/www/html/config.php (sola lettura).
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers \
    && { echo 'ServerTokens Prod'; echo 'ServerSignature Off'; } > /etc/apache2/conf-enabled/sicurezza.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/salvareel.ini"

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --chown=root:root .htaccess schema.sql ./
COPY --chown=root:root src/ src/
COPY --chown=root:root public/ public/
COPY --chown=root:root bin/ bin/
COPY --chown=root:root migrazioni/ migrazioni/
