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

# Install and make wp-cli binary available and executable by all users.
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
RUN chmod a+rx /usr/local/bin/wp

# If PHP_VERSION is 7.4, the use wp-cli to update wordpress to version 6.2.
# No image is available for wordpress 6.2 with PHP 7.4: this works around the issue.
RUN if [ "${PHP_VERSION}" = "7.4" ]; then \
    wp core update --version=6.2 --force; \
    fi
