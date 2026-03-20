#!/usr/bin/env bash
# Runs PHPUnit for the redirect-tracking module inside a Docker replica of the CI
# environment. Invoke via: make test
#
# Dependencies:
#   - tapbuy/data-scrubber must be cloned at ../data-scrubber
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
IMAGE="tapbuy-ci-php83"
DATA_SCRUBBER="${SCRIPT_DIR}/../data-scrubber"

if ! docker image inspect "$IMAGE" > /dev/null 2>&1; then
    echo "Building Docker image ${IMAGE} (first run only)..."
    docker build -t "$IMAGE" "$SCRIPT_DIR"
fi

if [ ! -d "$DATA_SCRUBBER" ]; then
    echo "Error: data-scrubber not found at ${DATA_SCRUBBER}" >&2
    echo "Clone tapbuy/data-scrubber next to this module directory." >&2
    exit 1
fi

if [ ! -f "${SCRIPT_DIR}/auth.json" ]; then
    echo "Error: auth.json not found in this module directory." >&2
    echo "Copy auth.json.dist to auth.json and fill in your Magento repo credentials." >&2
    exit 1
fi

docker run --rm \
    -v "tapbuy-magento-2.4.7-p5-php83:/magento" \
    -v "${SCRIPT_DIR}:/module:ro" \
    -v "${DATA_SCRUBBER}:/tapbuy-data-scrubber:ro" \
    -v "${SCRIPT_DIR}/auth.json:/root/.composer/auth.json:ro" \
    "$IMAGE" \
    bash /module/docker-entrypoint.sh
