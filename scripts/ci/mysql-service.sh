#!/usr/bin/env bash
# Shared lifecycle for the repository-owned MySQL service (docker-compose.yml).
# Sourced by run-integration.sh and run-consumer-verification.sh so that local
# runs, CI, and the Consumer Verification Harness use one definition and one
# lifecycle. Not meant to be executed directly.
#
#   mysql_service_start   validates prerequisites, starts a run-scoped Compose
#                         project, waits for service-level health, and exports
#                         PRODUCT_TEST_DB_* and PRODUCT_TEST_EXPECTED_VERSION
#   mysql_service_stop    tears the project down with `down -v` (idempotent)
#   mysql_service_residue verifies no test database remains; fails otherwise

MYSQL_SERVICE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

mysql_dc() { docker compose -p "$MYSQL_SERVICE_PROJECT" -f "$MYSQL_SERVICE_ROOT/docker-compose.yml" "$@"; }

mysql_service_start() {
    local tool
    for tool in docker openssl php; do
        command -v "$tool" >/dev/null 2>&1 || { echo "ERROR: missing prerequisite: $tool" >&2; return 2; }
    done
    docker compose version >/dev/null 2>&1 || { echo "ERROR: the docker compose plugin is required" >&2; return 2; }

    export PRODUCT_TEST_MYSQL_VERSION="${PRODUCT_TEST_MYSQL_VERSION:-8.0.16}"
    PRODUCT_TEST_DB_PASSWORD="$(openssl rand -hex 16)"
    export PRODUCT_TEST_DB_PASSWORD
    MYSQL_SERVICE_PROJECT="${PRODUCT_TEST_COMPOSE_PROJECT:-phpproduct-${PRODUCT_TEST_MYSQL_VERSION//./-}-$$}"

    echo "Starting MySQL ${PRODUCT_TEST_MYSQL_VERSION} (project ${MYSQL_SERVICE_PROJECT})"
    if ! mysql_dc up -d --wait --wait-timeout 300 >/dev/null; then
        echo "ERROR: MySQL service did not become healthy" >&2
        mysql_dc logs --tail 40 mysql >&2 || true
        return 1
    fi

    local endpoint
    endpoint="$(mysql_dc port mysql 3306 | head -1)"
    [ -n "$endpoint" ] || { echo "ERROR: could not discover the published MySQL endpoint" >&2; return 1; }
    export PRODUCT_TEST_DB_HOST="${endpoint%:*}"
    export PRODUCT_TEST_DB_PORT="${endpoint##*:}"
    export PRODUCT_TEST_DB_USER="root"
    export PRODUCT_TEST_EXPECTED_VERSION="${PRODUCT_TEST_MYSQL_VERSION}"
    echo "Endpoint ${PRODUCT_TEST_DB_HOST}:${PRODUCT_TEST_DB_PORT}"
}

mysql_service_stop() {
    if [ -n "${MYSQL_SERVICE_PROJECT:-}" ]; then
        mysql_dc down -v --remove-orphans >/dev/null 2>&1 || true
    fi
}

mysql_service_residue() {
    local left
    left="$(mysql_dc exec -T -e MYSQL_PWD="$PRODUCT_TEST_DB_PASSWORD" mysql mysql -uroot -N \
        -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE 'product\\_%'" | tail -1)"
    if [ "$left" != "0" ]; then
        echo "ERROR: ${left} test database(s) remain" >&2
        return 1
    fi
    echo "Residue check: clean"
}
