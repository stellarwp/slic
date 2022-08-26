#!/bin/bash

. $NVM_DIR/nvm.sh

NVMRC=.nvmrc

if [[ -f "$NVMRC" ]]; then
	nvm install $(cat $NVMRC)
	nvm use
fi