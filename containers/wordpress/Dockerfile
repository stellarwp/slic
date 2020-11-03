ARG WORDPRESS_IMAGE_VERSION="latest"

FROM wordpress:${WORDPRESS_IMAGE_VERSION}

# Pull in an helper library to install PHP extensions.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
# Install the XDebug extension.
RUN install-php-extensions xdebug
# Configure XDebug to autostart on all requests.
RUN echo 'xdebug.remote_autostart=1' >>  /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
# Make the XDebug configuration file world-read/writeable as the user updating it might not be a sudo-er.
RUN chmod a+rw /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Change the entrypoint to enable/disable XDebug depending on the XDEBUG_DISABLE env var.
COPY tric-entrypoint.sh /usr/local/bin/tric-entrypoint.sh
# Make the tric entrypoing world-executable as the user that executes it might not be a sudo-er.
RUN chmod a+x /usr/local/bin/tric-entrypoint.sh
# Change the default entrypoint to be the tric one.
ENTRYPOINT ["/usr/local/bin/tric-entrypoint.sh"]
# We need to explicitly set the CMD since we changed the ENTRYPOINT.
CMD ["apache2-foreground"]
