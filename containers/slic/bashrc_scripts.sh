# Some aliases to save some typing.
alias c="vendor/bin/codecept -c $(if [ -f 'codeception.slic.yml' ]; then echo 'codeception.slic.yml'; else echo 'codeception.tric.yml'; fi)"
alias cr="vendor/bin/codecept -c $(if [ -f 'codeception.slic.yml' ]; then echo 'codeception.slic.yml'; else echo 'codeception.tric.yml'; fi) run"

# Returns the path to the PHP version configuration file.
function xdebug_config_file(){
  echo "$(php --ini | grep xdebug | cut -d, -f1)"
}

# Activates the XDebug extension.
function xon(){
  xdebug-on
  echo "#!/bin/bash" > ~/xdebug-setting.sh
  echo "xdebug-on" >> ~/xdebug-setting.sh
  chmod +x ~/xdebug-setting.sh
}

# Deactivates the XDebug extension completely.
function xoff(){
  xdebug-off
  echo "#!/bin/bash" > ~/xdebug-setting.sh
  echo "xdebug-off" >> ~/xdebug-setting.sh
  chmod +x ~/xdebug-setting.sh
}

XDEBUG_FILE=~/xdebug-setting.sh
if [ -f "$XDEBUG_FILE" ]; then
  . ~/xdebug-setting.sh
else
  xoff
fi

echo "  c    = codecept"
echo "  cr   = codecept run"
echo "  xon  = turn xdebug on"
echo "  xoff = turn xdebug off"
echo ""