ARG WORDPRESS_IMAGE_VERSION=wordpress/apache

FROM wordpress:6.0.2-php7.4-apache
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod a+x /usr/local/bin/install-php-extensions && install-php-extensions xdebug
COPY xdebug-on.sh /usr/local/bin/xdebug-on
COPY xdebug-off.sh /usr/local/bin/xdebug-off
RUN chmod a+x /usr/local/bin/xdebug-on && \
    chmod a+x /usr/local/bin/xdebug-off && \
    echo "xdebug.mode=develop,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    xdebug-off
RUN chmod -R a+rwx /usr/local/etc/php/conf.d
