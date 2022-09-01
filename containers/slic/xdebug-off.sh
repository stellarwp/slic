#!/bin/sh

xdebug_config_file=$(php --ini | grep xdebug | cut -d, -f1)
sed -i '/^zend_extension/ s/zend_extension/;zend_extension/g' "$xdebug_config_file"
php -v
echo ""
echo "XDebug is \033[31moff\033[0m."
echo ""