ARG WORDPRESS_IMAGE_VERSION=wordpress/apache
ARG PHP_VERSION=7.4
ARG WP_VERSION=6.1

FROM wordpress:${WP_VERSION}-php${PHP_VERSION}-apache
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod a+x /usr/local/bin/install-php-extensions && install-php-extensions xdebug
COPY xdebug-on.sh /usr/local/bin/xdebug-on
COPY xdebug-off.sh /usr/local/bin/xdebug-off
RUN chmod a+x /usr/local/bin/xdebug-on && \
    chmod a+x /usr/local/bin/xdebug-off && \
    xdebug-off
RUN chmod -R a+rwx /usr/local/etc/php/conf.d
# Use our own ini configuration file to set up some PHP default.
COPY ./php.ini /usr/local/etc/php/conf.d/999-slic.ini
