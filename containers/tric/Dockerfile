# Source Composer 1 and 2 from the respective images, multi-layer builds.
FROM composer:1 AS composer1
FROM composer:2 AS composer2

# Use a fixed PHP version as base!
FROM php:7.4.29

# Install and make wp-cli binary available and executable by all users.
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
RUN chmod a+rx /usr/local/bin/wp

# The mlocati/docker-php-extension-installer will install PHP extensions setting up requirements correctly and cleaning up after.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod a+x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd xdebug pdo pdo_mysql mysqli zip uopz pcntl sockets

# Install some more packages required by wp-cli and Composer.
RUN apt-get update && apt-get install -y \
    default-mysql-client curl git zip unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \

# Use our own ini configuration file to set up some PHP default.
COPY php.ini /usr/local/etc/php/conf.d/docker-php-defaults.ini

# Configure the uopz extension.
COPY docker-php-ext-uopz.ini /usr/local/etc/php/conf.d/docker-php-ext-uopz.ini

# Add the XDebug control scripts.
COPY xdebug-on.sh /usr/local/bin/xdebug-on
COPY xdebug-off.sh /usr/local/bin/xdebug-off
RUN chmod a+x /usr/local/bin/xdebug-on && \
    chmod a+x /usr/local/bin/xdebug-off

# Make the PHP configuration directory recursively readable and writable to allow all users to activate and deactivate XDebug.
RUN chmod -R a+rwx /usr/local/etc/php/conf.d
RUN echo "xdebug.mode=develop,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini#A command that will keep the container running and alive.
RUN xdebug-off

# Install Composer 1 and 2 from the respective images and make them world-executable.
COPY --from=composer1 /usr/bin/composer /usr/local/bin/composer1
COPY --from=composer2 /usr/bin/composer /usr/local/bin/composer
RUN chmod a+x /usr/local/bin/composer1 && chmod a+x /usr/local/bin/composer

# Create a `tric` user and group fixuid will use.
RUN [ $(getent group 1000) ] || addgroup --gid 1000 tric && \
    [ $(id -u 1000) ] || adduser --uid 1000 --gid 1000 --home /home/tric --shell /bin/sh --disabled-password --gecos "" tric && \
    mkdir -p /home/tric && \
    curl -SsL https://github.com/boxboat/fixuid/releases/download/v0.5.1/fixuid-0.5.1-linux-amd64.tar.gz | tar -C /usr/local/bin -xzf - && \
    chown root:root /usr/local/bin/fixuid && \
    chmod 4755 /usr/local/bin/fixuid && \
    mkdir -p /etc/fixuid
COPY fixuid.yml /etc/fixuid/config.yml
COPY .bashrc /home/tric/.bashrc
COPY .bashrc /root/.bashrc
COPY bashrc_scripts.sh /home/tric/bashrc_scripts.sh

COPY tric-entrypoint.sh /usr/local/bin/tric-entrypoint.sh
RUN chmod a+x /usr/local/bin/tric-entrypoint.sh
ENTRYPOINT ["/usr/local/bin/tric-entrypoint.sh"]

USER tric:tric
