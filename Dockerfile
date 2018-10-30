# Dockerfile for Expungement Generator's frontend.

FROM php:7.2-apache-stretch

RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y git nano wget python2.7 libfontconfig poppler-utils cron zlib1g-dev nano unattended-upgrades apt-listchanges && \
    ln -s /usr/bin/pdftotext /usr/local/bin/pdftotext && \
    wget https://bitbucket.org/ariya/phantomjs/downloads/phantomjs-2.1.1-linux-x86_64.tar.bz2 && \
    tar -xvjf phantomjs-2.1.1-linux-x86_64.tar.bz2 && \
    mv phantomjs-2.1.1-linux-x86_64 /usr/local/share && \
    ln -sf /usr/local/share/phantomjs-2.1.1-linux-x86_64/bin/phantomjs /usr/local/bin

RUN git clone https://github.com/casperjs/casperjs.git /usr/local/casperjs --single-branch && \
    cd /usr/local/casperjs && \
    ln -sf `pwd`/bin/casperjs /usr/local/bin/casperjs && \
    cd /usr/local/include && \
    ln -s /usr/bin/python2.7 /usr/bin/python

RUN git clone https://github.com/CLSPhila/casperscraping /usr/local/include/cpcmsNavigate --single-branch && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli && docker-php-ext-install zip

COPY ./eg-cron /etc/cron.d/eg-cron

RUN chmod 0644 /etc/cron.d/eg-cron && \
    touch /var/log/cron.log && \
    rm /var/www/html/*

COPY Expungement-Generator/ /var/www/html/
COPY ./php.ini /usr/local/etc/php/php.ini
COPY ./docker-config.php /var/www/html/config.php

RUN mkdir -p /var/www/html/data && \
    mkdir -p /var/www/html/docketsheets && \
    useradd -ms /bin/bash eg_user && \
    chown eg_user:eg_user /var/www/html/data && \
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
