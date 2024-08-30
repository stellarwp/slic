ARG WORDPRESS_IMAGE_VERSION=wordpress/apache
ARG PHP_VERSION=7.4
ARG WP_VERSION=6.1

FROM ghcr.io/wordpress/wordpress:${WP_VERSION}-php${PHP_VERSION}-apache
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod a+x /usr/local/bin/install-php-extensions && install-php-extensions xdebug
COPY xdebug-on.sh /usr/local/bin/xdebug-on
COPY xdebug-off.sh /usr/local/bin/xdebug-off
RUN chmod a+x /usr/local/bin/xdebug-on && \
    chmod a+x /usr/local/bin/xdebug-off && \
    xdebug-off
RUN chmod -R a+rwx /usr/local/etc/php/conf.d

# Install and make wp-cli binary available and executable by all users.
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
RUN chmod a+rx /usr/local/bin/wp

# No image for WordPress 6.2+ is available for PHP 7.4.
# If PHP_VERSION is 7.4, update WordPress to 6.2 using wp-cli.
# Weird syntax? POSIX compliant sh.
RUN if echo "${PHP_VERSION}" | grep -q '^7.4'; then \
    wp --allow-root --path=/usr/src/wordpress core download --version=6.2 --force; \
    fi
