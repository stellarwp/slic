#!/bin/sh

pcov_config_file=$(php --ini | grep pcov | cut -d, -f1)

if [ -z "$pcov_config_file" ]; then
  echo "PCOV config file not found."
  exit 1
fi

sed -i '/^extension=.*pcov/ s/^/;/' "$pcov_config_file"

if php -m | grep -iq '^pcov$'; then
  echo "PCOV could not be disabled."
  exit 1
fi

php -v
pkill -o -USR2 php-fpm
/etc/init.d/apache2 reload > /dev/null 2>&1
exit 0
