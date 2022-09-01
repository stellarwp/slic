# Some aliases to save some typing.
alias c="vendor/bin/codecept -c $(if [ -f 'codeception.slic.yml' ]; then echo 'codeception.slic.yml'; else echo 'codeception.tric.yml'; fi)"
alias cr="vendor/bin/codecept -c $(if [ -f 'codeception.slic.yml' ]; then echo 'codeception.slic.yml'; else echo 'codeception.tric.yml'; fi) run"

# Returns the path to the PHP version configuration file.
function xdebug_config_file(){
  echo "$(php --ini | grep xdebug | cut -d, -f1)"
}

# Activates the XDebug extension.
function xon(){
  xdebug_config_file=$(xdebug_config_file)
  sed -i '/^;zend_extension/ s/;zend_extension/zend_extension/g' "$xdebug_config_file"
  php -v
}

# Deactivates the XDebug extension completely.
function xoff(){
  xdebug_config_file=$(xdebug_config_file)
  sed -i '/^zend_extension/ s/zend_extension/;zend_extension/g' "$xdebug_config_file"
  php -v
}

xoff

echo ""
echo "  c = codecept"
echo "  cr = codecept run"
echo "  xon = turn xdebug on"
echo "  xoff = turn xdebug off"
echo ""