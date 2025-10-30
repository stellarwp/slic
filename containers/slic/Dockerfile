ARG PHP_VERSION=7.4

# Source Composer 1 and 2 from the respective images, multi-stage builds.
FROM composer:1 AS composer1
FROM composer:2 AS composer2

FROM php:${PHP_VERSION}

ARG NODE_VERSION=18.17.0
ARG NVM_VERSION=v0.40.1
ARG IPE_GD_WITHOUTAVIF=1

SHELL ["/bin/bash", "-eou", "pipefail", "-c"]

# -------------------------------
# WP-CLI & PHP extension installer
# -------------------------------
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod a+rx /usr/local/bin/wp /usr/local/bin/install-php-extensions

# Disable AVIF for GD https://github.com/mlocati/docker-php-extension-installer#configuration
ENV IPE_GD_WITHOUTAVIF=${IPE_GD_WITHOUTAVIF}
RUN install-php-extensions xdebug pdo pdo_mysql mysqli zip uopz pcntl sockets intl exif gd

# -------------------------------
# NVM environment variables
# -------------------------------
ENV NVM_VERSION=${NVM_VERSION}
ENV NVM_DIR=/usr/local/bin/.nvm

# -------------------------------
# Dependencies needed for both NVM and NodeSource
# -------------------------------
RUN apt-get update && \
    apt-get install -yqq --no-install-recommends \
        ca-certificates curl gpg git zip unzip iproute2 \
        libnss3 libnspr4 libdbus-1-3 libatk1.0-0 libatk-bridge2.0-0 \
        libcups2 libdrm2 libxkbcommon0 libatspi2.0-0 libxcomposite1 \
        libxdamage1 libxext6 libxfixes3 libxrandr2 libgbm1 \
        libpango-1.0-0 libcairo2 libasound2 less default-mysql-client && \
    mkdir -p $NVM_DIR/cache /etc/apt/keyrings /cache /composer-cache && \
    chmod a+rwx $NVM_DIR/cache /cache /composer-cache && \
    chmod 755 /etc/apt/keyrings

# -------------------------------
# Install NVM
# -------------------------------
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/${NVM_VERSION}/install.sh | bash && \
    chmod a+x $NVM_DIR/nvm.sh

# -------------------------------
# Install Node.js via NodeSource
# -------------------------------
RUN export NODE_MAJOR=$(echo ${NODE_VERSION} | cut -d. -f1) && \
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg && \
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" \
        | tee /etc/apt/sources.list.d/nodesource.list && \
    apt-get update && apt-get install -yqq --no-install-recommends nodejs && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# -------------------------------
# PHP uopz extension & XDebug
# -------------------------------
COPY ./docker-php-ext-uopz.ini /usr/local/etc/php/conf.d/docker-php-ext-uopz.ini
COPY ./xdebug-on.sh /usr/local/bin/xdebug-on
COPY ./xdebug-off.sh /usr/local/bin/xdebug-off

RUN chmod a+x /usr/local/bin/xdebug-on /usr/local/bin/xdebug-off && \
    chmod -R a+rwx /usr/local/etc/php/conf.d && xdebug-off

# -------------------------------
# Composer 1 & 2
# -------------------------------
COPY --from=composer1 /usr/bin/composer /usr/local/bin/composer1
COPY --from=composer2 /usr/bin/composer /usr/local/bin/composer

RUN chmod a+x /usr/local/bin/composer /usr/local/bin/composer1

# -------------------------------
# Slic user & fixuid
# -------------------------------
ARG TARGETPLATFORM
RUN groupadd -g 1000 slic 2>/dev/null || true && \
    useradd -u 1000 -g 1000 -m -s /bin/bash slic 2>/dev/null || true && \
    case "$TARGETPLATFORM" in \
        "linux/amd64") FIXUID_ARCH=amd64 ;; \
        "linux/arm64") FIXUID_ARCH=arm64 ;; \
        *) echo "Unsupported platform: $TARGETPLATFORM" && exit 1 ;; \
    esac && \
    curl -SsL https://github.com/boxboat/fixuid/releases/download/v0.6.0/fixuid-0.6.0-linux-$FIXUID_ARCH.tar.gz \
        | tar -C /usr/local/bin -xzf - && \
    chown root:root /usr/local/bin/fixuid && chmod 4755 /usr/local/bin/fixuid && \
    mkdir -p /etc/fixuid

COPY ./fixuid.yml /etc/fixuid/config.yml
COPY ./.bashrc /home/slic/.bashrc
COPY ./.bashrc /root/.bashrc
COPY ./bashrc_scripts.sh /home/slic/bashrc_scripts.sh

# Ensure NVM directory is owned by slic
RUN chown -R slic:slic $NVM_DIR

# -------------------------------
# Entrypoint
# -------------------------------
COPY ./slic-entrypoint.sh /usr/local/bin/slic-entrypoint.sh
RUN chmod a+x /usr/local/bin/slic-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/slic-entrypoint.sh"]

USER slic:slic
