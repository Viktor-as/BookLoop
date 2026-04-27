#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
APP_ROOT="/var/www/html"
JWT_PRIVATE="${APP_ROOT}/config/jwt/private.pem"
FIXTURES_MARKER="${APP_ROOT}/var/.fixtures-loaded"

wait_for_mysql() {
    echo "[entrypoint] Waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."
    local attempts=0
    until nc -z "${DB_HOST}" "${DB_PORT}" >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ "${attempts}" -gt 60 ]; then
            echo "[entrypoint] MySQL did not become reachable in time" >&2
            exit 1
        fi
        sleep 1
    done
    echo "[entrypoint] MySQL is reachable."
}

ensure_jwt_keys() {
    if [ ! -f "${JWT_PRIVATE}" ]; then
        echo "[entrypoint] Generating Lexik JWT keypair ..."
        mkdir -p "${APP_ROOT}/config/jwt"
        php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
    fi
}

run_migrations() {
    echo "[entrypoint] Running migrations on main database ..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
}

load_fixtures_once() {
    if [ "${SEED_FIXTURES:-1}" != "1" ]; then
        echo "[entrypoint] SEED_FIXTURES=0 — skipping demo data load."
        return
    fi
    if [ -f "${FIXTURES_MARKER}" ]; then
        echo "[entrypoint] Fixtures marker present — skipping seed."
        return
    fi
    echo "[entrypoint] Loading demo fixtures into main database ..."
    php bin/console doctrine:fixtures:load --no-interaction
    mkdir -p "$(dirname "${FIXTURES_MARKER}")"
    touch "${FIXTURES_MARKER}"
}

warm_cache() {
    echo "[entrypoint] Warming Symfony cache ..."
    php bin/console cache:warmup --no-interaction --no-ansi
}

fix_permissions() {
    # Warmup and fixtures run as root; Apache workers run as www-data.
    chown -R www-data:www-data "${APP_ROOT}/var" "${APP_ROOT}/config/jwt" || true
}

mkdir -p "${APP_ROOT}/var" "${APP_ROOT}/config/jwt"
fix_permissions

wait_for_mysql
ensure_jwt_keys
run_migrations
load_fixtures_once
warm_cache
fix_permissions

echo "[entrypoint] Bootstrap complete — handing off to: $*"
exec "$@"
