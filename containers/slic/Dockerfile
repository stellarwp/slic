ARG PHP_VERSION=7.4

# Source Composer 1 and 2 from the respective images, multi-stage builds.
FROM ghcr.io/composer/docker:1 AS composer1
FROM ghcr.io/composer/docker:2 AS composer2

FROM php:${PHP_VERSION}

ARG NODE_VERSION=18.17.0
ARG NVM_VERSION=v0.40.1
ARG IPE_GD_WITHOUTAVIF=1
ARG TARGETPLATFORM

SHELL ["/bin/bash", "-eou", "pipefail", "-c"]

# -------------------------------
# Environment variables
# -------------------------------
# Disable AVIF for GD https://github.com/mlocati/docker-php-extension-installer#configuration
ENV IPE_GD_WITHOUTAVIF=${IPE_GD_WITHOUTAVIF}
ENV NVM_VERSION=${NVM_VERSION}
ENV NVM_DIR=/usr/local/bin/.nvm
ENV IPE_CACHE_DIR=/tmp/ipe-cache

# -------------------------------
# WP-CLI & PHP extension installer
# -------------------------------
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod a+rx /usr/local/bin/wp /usr/local/bin/install-php-extensions

# -------------------------------
# PHP extensions (heavy compilation step with cache mount)
# -------------------------------
RUN --mount=type=cache,target=/tmp/ipe-cache,sharing=locked \
    install-php-extensions xdebug pdo pdo_mysql mysqli zip uopz pcntl sockets intl exif gd

# -------------------------------
# System dependencies (with cache mount for speed)
# -------------------------------
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean && \
    apt-get update && \
    apt-get install -yqq --no-install-recommends \
        ca-certificates curl git zip unzip iproute2 \
        libnss3 libnspr4 libdbus-1-3 libatk1.0-0 libatk-bridge2.0-0 \
        libcups2 libdrm2 libxkbcommon0 libatspi2.0-0 libxcomposite1 \
        libxdamage1 libxext6 libxfixes3 libxrandr2 libgbm1 \
        libpango-1.0-0 libcairo2 libasound2 less default-mysql-client && \
    mkdir -p $NVM_DIR/cache /cache /composer-cache && \
    chmod a+rwx $NVM_DIR/cache /cache /composer-cache

# -------------------------------
# Install NVM
# -------------------------------
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/${NVM_VERSION}/install.sh | bash && \
    chmod a+x $NVM_DIR/nvm.sh

# -------------------------------
# Install Node.js via precompiled tarballs
# -------------------------------
RUN set -eux; \
    case "${TARGETPLATFORM}" in \
        "linux/amd64") ARCH="x64";; \
        "linux/arm64") ARCH="arm64";; \
        *) echo "Unsupported platform: ${TARGETPLATFORM}"; exit 1;; \
    esac; \
    NODE_DISTRO="node-v${NODE_VERSION}-linux-${ARCH}.tar.xz"; \
    curl -fsSL "https://nodejs.org/dist/v${NODE_VERSION}/${NODE_DISTRO}" -o "/tmp/${NODE_DISTRO}"; \
    tar -xJf "/tmp/${NODE_DISTRO}" -C /usr/local --strip-components=1; \
    rm "/tmp/${NODE_DISTRO}"; \
    node -v; npm -v

# -------------------------------
# Slic user & fixuid setup
# -------------------------------
RUN groupadd -g 1000 slic 2>/dev/null || true && \
    useradd -u 1000 -g 1000 -m -s /bin/bash slic 2>/dev/null || true && \
    case "$TARGETPLATFORM" in \
        "linux/amd64") FIXUID_ARCH=amd64 ;; \
        "linux/arm64") FIXUID_ARCH=arm64 ;; \
        *) echo "Unsupported platform: $TARGETPLATFORM" && exit 1 ;; \
    esac && \
    curl -SsL https://github.com/boxboat/fixuid/releases/download/v0.6.0/fixuid-0.6.0-linux-$FIXUID_ARCH.tar.gz \
        | tar -C /usr/local/bin -xzf - && \
    chown root:root /usr/local/bin/fixuid && \
    chmod 4755 /usr/local/bin/fixuid && \
    mkdir -p /etc/fixuid

# -------------------------------
# Composer 1 & 2 from multi-stage builds
# -------------------------------
COPY --from=composer1 /usr/bin/composer /usr/local/bin/composer1
COPY --from=composer2 /usr/bin/composer /usr/local/bin/composer

# -------------------------------
# Local config files (moved late for better caching)
# -------------------------------
COPY ./docker-php-ext-uopz.ini /usr/local/etc/php/conf.d/docker-php-ext-uopz.ini
COPY ./xdebug-on.sh /usr/local/bin/xdebug-on
COPY ./xdebug-off.sh /usr/local/bin/xdebug-off
COPY ./fixuid.yml /etc/fixuid/config.yml
COPY ./.bashrc /home/slic/.bashrc
COPY ./.bashrc /root/.bashrc
COPY ./bashrc_scripts.sh /home/slic/bashrc_scripts.sh
COPY ./slic-entrypoint.sh /usr/local/bin/slic-entrypoint.sh

# -------------------------------
# Final permissions & setup
# -------------------------------
RUN chmod a+x /usr/local/bin/xdebug-on /usr/local/bin/xdebug-off \
             /usr/local/bin/composer /usr/local/bin/composer1 \
             /usr/local/bin/slic-entrypoint.sh && \
    chmod -R a+rwx /usr/local/etc/php/conf.d && \
    chown -R slic:slic $NVM_DIR && \
    xdebug-off

ENTRYPOINT ["/usr/local/bin/slic-entrypoint.sh"]

USER slic:slic
