#!/usr/bin/env bash
# Runs PHPUnit for the redirect-tracking module inside a Docker replica of the CI
# environment. Invoke via: make test
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
IMAGE="tapbuy-ci-php83"

if ! docker image inspect "$IMAGE" > /dev/null 2>&1; then
    echo "Building Docker image ${IMAGE} (first run only)..."
    docker build -t "$IMAGE" "$SCRIPT_DIR"
fi

if [ ! -f "${SCRIPT_DIR}/auth.json" ]; then
    echo "Error: auth.json not found in this module directory." >&2
    echo "Copy auth.json.dist to auth.json and fill in your Magento repo credentials." >&2
    exit 1
fi

docker run --rm \
    -v "tapbuy-magento-2.4.7-p5-php83:/magento" \
    -v "${SCRIPT_DIR}:/module:ro" \
    -v "${SCRIPT_DIR}/auth.json:/root/.composer/auth.json:ro" \
    "$IMAGE" \
    bash /module/docker-entrypoint.sh
