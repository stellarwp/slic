#!/usr/bin/env bash

# This file is just a proxy to call the `npm` binary that will, but, take care of fixing file mode issues before.

# If the `FIXUID` env var is set to `1`, default value, then fix UIDs.
test "${FIXUID:-1}" != "0" && eval "$( fixuid > /dev/null 2>&1 )"

cd /project/${TRIC_CURRENT_PROJECT_SUBDIR}

npm "$@"

# Output error logs if present.
if compgen -G "/home/node/.npm/_logs/*.log" > /dev/null; then
  echo "---------------------------------------"
  echo "Error log found. Here are the contents (excluding the overly verbose saveTree lines):"
  echo "---------------------------------------"
  cat /home/node/.npm/_logs/*.log | grep -v "saveTree"
  echo "---------------------------------------"
  echo "End of error log"
  echo "---------------------------------------"
fi
