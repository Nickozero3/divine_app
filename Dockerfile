FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    libapache2-mod-php \
    php-mysql \
    php-cli \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    unzip \
    curl \
    nano \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

RUN rm -f /var/www/html/index.html

COPY db/ /var/www/db/
COPY src/ /var/www/html/

RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/mods-enabled/dir.conf

RUN chown -R www-data:www-data /var/www/html
RUN chown www-data:www-data /var/www/.env || true

EXPOSE 80

CMD ["apachectl", "-D", "FOREGROUND"]