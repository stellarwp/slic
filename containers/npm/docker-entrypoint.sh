#!/usr/bin/env bash

# This file is just a proxy to call the `npm` binary that will, but, take care of fixing file mode issues before.

# If the `FIXUID` env var is set to `1`, default value, then fix UIDs.
test "${FIXUID:-1}" != "0" && eval "$( fixuid )"

npm --prefix /project "$@"
