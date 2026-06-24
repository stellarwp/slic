#!/bin/sh

pcov_config_file=$(php --ini | grep pcov | cut -d, -f1)

if [ -z "$pcov_config_file" ]; then
  echo "PCOV config file not found."
  exit 1
fi

sed -i '/^;extension=.*pcov/ s/^;//' "$pcov_config_file"

if ! php -m | grep -iq '^pcov$'; then
  echo "PCOV could not be enabled."
  exit 1
fi

php -v
