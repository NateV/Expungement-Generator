# Dockerfile for Expungement Generator's frontend.

FROM php:8.2-apache-bullseye

RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y git nano wget python2.7 libfontconfig poppler-utils cron zlib1g-dev nano unattended-upgrades apt-listchanges libzip-dev zlib1g-dev && \
    ln -s /usr/bin/pdftotext /usr/local/bin/pdftotext

RUN cd /usr/local/include && \
    ln -s /usr/bin/python2.7 /usr/bin/python


RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli && docker-php-ext-install zip

COPY ./egcron /etc/cron.d/egcron

RUN chmod 0644 /etc/cron.d/egcron && \
    touch /var/log/cron.log

COPY Expungement-Generator/ /var/www/html/
COPY ./php.ini /usr/local/etc/php/php.ini
COPY ./docker-config.php /var/www/html/config.php

RUN mkdir -p /data && \
    mkdir -p /var/www/html/docketsheets && \
    useradd -ms /bin/bash eg_user && \
    chown eg_user:eg_user /data && \
    chown eg_user:eg_user /var/www/html/docketsheets && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    mkdir -p /var/www/html/vendor && \
    chown -R eg_user:eg_user /var/www/html/vendor

USER eg_user:eg_user

RUN composer install

USER root

RUN cp /var/www/html/TemplateProcessor.php /var/www/html/vendor/phpoffice/phpword/src/PhpWord/ && \
    sed -i s/*:80/*:9090/ /etc/apache2/sites-available/000-default.conf && \
    sed -i s/80/9090/ /etc/apache2/ports.conf && \
    touch /var/run/apache2/apache2.pid && \
    chown eg_user:eg_user /var/run/apache2


USER eg_user:eg_user
