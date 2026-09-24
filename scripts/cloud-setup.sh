#!/usr/bin/env bash
# Prepares a fresh Linux container (e.g. Claude Code on the web) to run
# hexeduca's test suite and build. Idempotent: safe to run on every session.
set -euo pipefail

cd "$(dirname "$0")/.."

SUDO=""
if [ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1; then
    SUDO="sudo"
fi

if ! php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' >/dev/null 2>&1; then
    echo "==> Installing PHP 8.3"
    $SUDO apt-get update -y
    $SUDO apt-get install -y software-properties-common ca-certificates curl unzip
    $SUDO add-apt-repository -y ppa:ondrej/php
    $SUDO apt-get update -y
    $SUDO apt-get install -y \
        php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
        php8.3-sqlite3 php8.3-pgsql php8.3-intl php8.3-bcmath php8.3-redis
fi

if ! command -v composer >/dev/null 2>&1; then
    echo "==> Installing Composer"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/tmp --filename=composer
    $SUDO mv /tmp/composer /usr/local/bin/composer
fi

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist --no-progress

echo "==> Installing JS dependencies"
npm ci --no-audit --no-fund

if [ ! -f .env ]; then
    cp .env.example .env
fi
if ! grep -q '^APP_KEY=.\+' .env; then
    php artisan key:generate --force
fi

# A stale Vite hot file makes every page load assets from a dead dev server.
rm -f public/hot

echo "==> Ready. Run: php vendor/bin/pest --testsuite=Unit,Feature,Architecture"
