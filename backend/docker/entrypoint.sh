#!/bin/sh
set -e

cd /var/www/html

log() { echo "[fitmonth:entrypoint] $*"; }

# Установка пакетов идут напрямую, без bootstrap Laravel
if [ "$1" = "composer" ]; then
    if [ ! -f .env ]; then
        cp .env.docker .env
        log "created .env from .env.docker"
    fi
    exec "$@"
fi

# ---------------------------------------------------------------- .env setup
if [ ! -f .env ]; then
    cp .env.docker .env
    log "created .env from .env.docker"
fi

# ---------------------------------------------------------------- APP_KEY
if [ -f vendor/autoload.php ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
    log "generated APP_KEY"
fi

# ---------------------------------------------------------------- wait for MySQL
HOST="${DB_HOST:-mysql}"
DB="${DB_DATABASE:-fitmonth}"
USER="${DB_USERNAME:-fitmonth}"
PASS="${DB_PASSWORD:-secret}"

log "waiting for database at ${HOST}..."
tries=0
until php -r "try { new PDO('mysql:host=${HOST};dbname=${DB}', '${USER}', '${PASS}'); exit(0); } catch (Throwable \$e) { exit(1); }"; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
        log "database is not reachable, giving up"
        exit 1
    fi
    sleep 2
done
log "database is up"

# ---------------------------------------------------------------- role
case "$1" in
    php-fpm)
        log "running migrations..."
        php artisan migrate --force --no-interaction

        if [ ! -f storage/app/.seeded ]; then
            log "seeding database (first boot)..."
            php artisan db:seed --force --no-interaction
            touch storage/app/.seeded
            log "seed completed"
        fi
        ;;
    *)
        # horizon & scheduler: wait until the web container has migrated + seeded
        i=0
        while [ ! -f storage/app/.seeded ] && [ "$i" -lt 120 ]; do
            sleep 2
            i=$((i + 1))
        done
        if [ -f storage/app/.seeded ]; then
            log "database ready, proceeding with: $*"
        else
            log "WARNING: seed marker not found after waiting, proceeding anyway"
        fi
        ;;
esac

exec "$@"
