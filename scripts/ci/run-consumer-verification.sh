#!/usr/bin/env bash
# Consumer Verification Harness gate (composer verify:consumer).
#
# Runs the harness twice, each from a clean consumer root, against the same
# repository-owned MySQL service lifecycle used by Integration (mysql-service.sh). Each
# clean run also smoke-executes every standalone example in examples/.
# Environment: PRODUCT_TEST_MYSQL_VERSION (default 8.0.16).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
# shellcheck source=scripts/ci/mysql-service.sh
source "$ROOT/scripts/ci/mysql-service.sh"

command -v composer >/dev/null 2>&1 || { echo "ERROR: missing prerequisite: composer" >&2; exit 2; }
bash "$ROOT/scripts/ci/check-consumer-verification-boundary.sh"

trap mysql_service_stop EXIT INT TERM
mysql_service_start

for run in 1 2; do
    consumer_root="$(mktemp -d "${TMPDIR:-/tmp}/maatify-product-consumer.XXXXXX")"
    echo "=== Consumer Verification Harness clean run #${run} (${consumer_root}) ==="
    status=0
    (
        set -euo pipefail
        cp -R "$ROOT/consumer-verification/." "$consumer_root/"
        sed "s|__PACKAGE_ROOT__|$ROOT|g" "$consumer_root/composer.json.template" > "$consumer_root/composer.json"
        cd "$consumer_root"
        # The fixture accepts the development ref of its path repository.
        composer validate --strict --no-check-all
        COMPOSER_HOME="$consumer_root/.composer-home" COMPOSER_CACHE_DIR="$consumer_root/.composer-cache" \
            composer update --no-interaction --prefer-dist --no-progress
        composer dump-autoload --optimize --strict-psr
        composer check-platform-reqs
        composer show maatify/php-product
        php verify.php
        # Standalone example smoke execution against the same MySQL service, through the
        # consumer root's production autoloader (installed package, no repository source).
        PRODUCT_EXAMPLE_AUTOLOAD="$consumer_root/vendor/autoload.php" php "$ROOT/scripts/ci/smoke-examples.php" "$ROOT"
    ) || status=$?
    rm -rf -- "$consumer_root"
    if [ "$status" -ne 0 ]; then
        echo "ERROR: Consumer Verification Harness clean run #${run} failed (status ${status})" >&2
        exit 1
    fi
done

mysql_service_residue
echo "Consumer Verification Harness passed twice from clean consumer states."
