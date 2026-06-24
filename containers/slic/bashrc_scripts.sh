# Some aliases to save some typing.
alias c="vendor/bin/codecept -c $(if [ -f 'codeception.slic.yml' ]; then echo 'codeception.slic.yml'; else echo 'codeception.tric.yml'; fi)"
alias cr="vendor/bin/codecept -c $(if [ -f 'codeception.slic.yml' ]; then echo 'codeception.slic.yml'; else echo 'codeception.tric.yml'; fi) run"
alias p="node_modules/.bin/playwright"

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

function pcovon(){
  pcov-on
  echo "#!/bin/bash" > ~/pcov-setting.sh
  echo "pcov-on" >> ~/pcov-setting.sh
  chmod +x ~/pcov-setting.sh
}

function pcovoff(){
  pcov-off
  echo "#!/bin/bash" > ~/pcov-setting.sh
  echo "pcov-off" >> ~/pcov-setting.sh
  chmod +x ~/pcov-setting.sh
}

XDEBUG_FILE=~/xdebug-setting.sh
if [ -f "$XDEBUG_FILE" ]; then
  . ~/xdebug-setting.sh
else
  xoff
fi

PCOV_FILE=~/pcov-setting.sh
if [ -f "$PCOV_FILE" ]; then
  . ~/pcov-setting.sh
else
  pcovoff
fi

echo "  c    = codecept"
echo "  cr   = codecept run"
echo "  p    = playwright"
echo "  xon  = turn xdebug on"
echo "  xoff = turn xdebug off"
echo "  pcovon  = turn pcov on"
echo "  pcovoff = turn pcov off"
echo ""
