#!/bin/bash

. $NVM_DIR/nvm.sh

NVMRC=.nvmrc

if [[ -f "$NVMRC" ]]; then
	VERSION=$(cat $NVMRC)
	HAS_VERSION=$(nvm ls --no-colors "$VERSION" | tail -1 | tr -d '\->*' | tr -d '[:space:]' )
	echo $HAS_VERSION
	if [[ "$HAS_VERSION" == "N/A" ]]; then
		nvm install $VERSION
	fi
	nvm use
fi