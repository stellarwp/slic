ARG PHP_VERSION=7.4

# Source Composer 1 and 2 from the respective images, multi-layer builds.
FROM composer:1 AS composer1
FROM composer:2 AS composer2

FROM php:${PHP_VERSION}

ARG NODE_VERSION=18.17.0
ARG NVM_VERSION=v0.40.1
# Disable AVIF for GD https://github.com/mlocati/docker-php-extension-installer#configuration
ARG IPE_GD_WITHOUTAVIF=true

SHELL ["/bin/bash", "-eou", "pipefail", "-c"]

# Install and make wp-cli binary available and executable by all users.
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
RUN chmod a+rx /usr/local/bin/wp

# The mlocati/docker-php-extension-installer will install PHP extensions setting up requirements correctly and cleaning up after.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod a+x /usr/local/bin/install-php-extensions && \
    install-php-extensions xdebug pdo pdo_mysql mysqli zip uopz pcntl sockets intl exif

RUN if [ ${IPE_GD_WITHOUTAVIF} = true ]; then \
      IPE_GD_WITHOUTAVIF=1 install-php-extensions gd; \
    else \
      install-php-extensions gd; \
    fi

# Install some more packages required by wp-cli, Composer and Playwright.
RUN apt-get update && apt-get upgrade -yqq && apt-get install -yqq --no-install-recommends --show-progress \
    default-mysql-client curl git zip unzip iproute2 \
    libnss3 libnspr4 libdbus-1-3 libatk1.0-0 libatk-bridge2.0-0 \
    libcups2 libdrm2 libxkbcommon0 libatspi2.0-0 libxcomposite1 \
    libxdamage1 libxext6 libxfixes3 libxrandr2 libgbm1 \
    libpango-1.0-0 libcairo2 libasound2 less \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure the uopz extension.
COPY ./docker-php-ext-uopz.ini /usr/local/etc/php/conf.d/docker-php-ext-uopz.ini

# Add the XDebug control scripts.
COPY ./xdebug-on.sh /usr/local/bin/xdebug-on
COPY ./xdebug-off.sh /usr/local/bin/xdebug-off
RUN chmod a+x /usr/local/bin/xdebug-on && \
    chmod a+x /usr/local/bin/xdebug-off

# Make the PHP configuration directory recursively readable and writable to allow all users to activate and deactivate XDebug.
RUN chmod -R a+rwx /usr/local/etc/php/conf.d
RUN xdebug-off

# Install nvm.
ENV NODE_VERSION=${NODE_VERSION}
ENV NVM_VERSION=${NVM_VERSION}
ENV NVM_DIR=/usr/local/bin/.nvm
RUN mkdir /usr/local/bin/.nvm && mkdir /usr/local/bin/.nvm/cache
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/${NVM_VERSION}/install.sh | bash
RUN bash -c '. $NVM_DIR/nvm.sh && nvm install $NODE_VERSION'
RUN bash -c '. $NVM_DIR/nvm.sh && nvm alias default $NODE_VERSION'
RUN bash -c '. $NVM_DIR/nvm.sh && nvm use default'

ENV PATH $NVM_DIR/versions/node/v$NODE_VERSION/bin:$NVM_DIR:$PATH
RUN chmod a+x $NVM_DIR/nvm.sh && chmod a+rwx $NVM_DIR/cache

# Install Composer 1 and 2 from the respective images and make them world-executable.
COPY --from=composer1 /usr/bin/composer /usr/local/bin/composer1
COPY --from=composer2 /usr/bin/composer /usr/local/bin/composer
RUN chmod a+x /usr/local/bin/composer1 && chmod a+x /usr/local/bin/composer

# Create a `slic` user and group fixuid will use.
RUN [ $(getent group 1000) ] || addgroup --gid 1000 slic && \
    [ $(id -u 1000) ] || adduser --uid 1000 --gid 1000 --home /home/slic --shell /bin/bash --disabled-password --gecos "" slic && \
    mkdir -p /home/slic && \
    curl -SsL https://github.com/boxboat/fixuid/releases/download/v0.5.1/fixuid-0.5.1-linux-amd64.tar.gz | tar -C /usr/local/bin -xzf - && \
    chown root:root /usr/local/bin/fixuid && \
    chmod 4755 /usr/local/bin/fixuid && \
    mkdir -p /etc/fixuid
COPY ./fixuid.yml /etc/fixuid/config.yml
COPY ./.bashrc /home/slic/.bashrc
COPY ./.bashrc /root/.bashrc
COPY ./bashrc_scripts.sh /home/slic/bashrc_scripts.sh

RUN chown -R slic:slic $NVM_DIR

# Create a /cache directory any user will be able to read, write and execute from.
RUN mkdir /cache && chmod a+rwx /cache
RUN mkdir /composer-cache && chmod a+rwx /composer-cache

COPY ./slic-entrypoint.sh /usr/local/bin/slic-entrypoint.sh
RUN chmod a+x /usr/local/bin/slic-entrypoint.sh
ENTRYPOINT ["/usr/local/bin/slic-entrypoint.sh"]

USER slic:slic
