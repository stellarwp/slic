#!/bin/sh
eval $(fixuid)

if [ "${PCOV_ENABLED:-0}" = "1" ]; then
  pcov-on > /dev/null || exit 1
else
  pcov-off > /dev/null || exit 1
fi

tail -f /dev/null
