ARG WORDPRESS_IMAGE_VERSION=wordpress/apache
ARG PHP_VERSION=7.4
ARG WP_VERSION=6.1

FROM wordpress:${WP_VERSION}-php${PHP_VERSION}-apache

# -------------------------------
# Environment variables
# -------------------------------
ENV IPE_CACHE_DIR=/tmp/ipe-cache

# -------------------------------
# WP-CLI & PHP extension installer
# -------------------------------
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp

# -------------------------------
# PHP extensions (with cache mount)
# -------------------------------
RUN --mount=type=cache,target=/tmp/ipe-cache,sharing=locked \
    chmod a+x /usr/local/bin/install-php-extensions && \
    chmod a+rx /usr/local/bin/wp && \
    install-php-extensions xdebug

# -------------------------------
# WordPress version update (PHP 7.4)
# -------------------------------
# No image for WordPress 6.2+ is available for PHP 7.4.
# If PHP_VERSION is 7.4, update WordPress to 6.2 using wp-cli.
# This runs before COPY so changes to scripts don't invalidate this layer.
RUN if echo "${PHP_VERSION}" | grep -q '^7.4'; then \
    wp --allow-root --path=/usr/src/wordpress core download --version=6.2 --force; \
    fi

# -------------------------------
# Local config files (moved late for better caching)
# -------------------------------
COPY xdebug-on.sh /usr/local/bin/xdebug-on
COPY xdebug-off.sh /usr/local/bin/xdebug-off

# -------------------------------
# Final permissions & setup
# -------------------------------
RUN chmod a+x /usr/local/bin/xdebug-on /usr/local/bin/xdebug-off && \
    chmod -R a+rwx /usr/local/etc/php/conf.d && \
    xdebug-off
