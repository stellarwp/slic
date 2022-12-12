FROM php:7.4-cli

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions uopz xdebug

COPY php.ini /usr/local/etc/php/conf.d/999-slic.ini
