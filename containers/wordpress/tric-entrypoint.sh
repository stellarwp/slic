#! /usr/bin/env bash

# If XDEBUG_DISABLE=1, then disable the XDebug extension completely.
if [ ! -n "${XDEBUG_DISABLE}" ]; then
  echo "Disabling XDebug extension ..."
  XDEBUG_INI_FILE="/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
  sed -i.bak '/^zend_extension.*xdebug.so/ s/zend_ex/;zend_ex/g' "${XDEBUG_INI_FILE}"
  echo -ne " \e[32mdone\e[0m"
fi

# Finally call the original image entrypoint passing through the CMD arguments, keep the arguments unpacked.
exec /usr/local/bin/docker-entrypoint.sh "$@"
