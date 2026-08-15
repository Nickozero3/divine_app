FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    libapache2-mod-php \
    php-mysql \
    php-cli \
    php-opcache \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    unzip \
    curl \
    nano \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers expires deflate
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN printf '%s\n' \
    'opcache.enable=1' \
    'opcache.enable_cli=0' \
    'opcache.memory_consumption=128' \
    'opcache.max_accelerated_files=10000' \
    'opcache.validate_timestamps=1' \
    'opcache.revalidate_freq=2' \
    > /etc/php/8.3/apache2/conf.d/99-divine-opcache.ini

RUN printf '%s\n' \
    '<IfModule mod_deflate.c>' \
    '  AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json image/svg+xml' \
    '</IfModule>' \
    '<IfModule mod_headers.c>' \
    '  Header append Vary Accept-Encoding' \
    '  <FilesMatch "\\.(?:css|js|ico|svg|woff2?)$">' \
    '    Header set Cache-Control "public, max-age=31536000, immutable"' \
    '  </FilesMatch>' \
    '</IfModule>' \
    '<IfModule mod_expires.c>' \
    '  ExpiresActive On' \
    '  ExpiresByType text/css "access plus 1 year"' \
    '  ExpiresByType application/javascript "access plus 1 year"' \
    '  ExpiresByType image/x-icon "access plus 1 year"' \
    '  ExpiresByType image/svg+xml "access plus 1 year"' \
    '</IfModule>' \
    > /etc/apache2/conf-available/divine-performance.conf \
    && a2enconf divine-performance

WORKDIR /var/www/html

RUN rm -f /var/www/html/index.html

COPY db/ /var/www/db/
COPY src/ /var/www/html/

RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/mods-enabled/dir.conf

RUN chown -R www-data:www-data /var/www/html
RUN chown www-data:www-data /var/www/.env || true

EXPOSE 80

CMD ["apachectl", "-D", "FOREGROUND"]
