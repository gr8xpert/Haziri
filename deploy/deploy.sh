#!/usr/bin/env bash
# Idempotent deploy for Plesk (spec §14). Run over SSH or from a Plesk Git post-deploy hook.
# Usage: APP_DIR=/var/www/vhosts/hazri.pk/httpdocs deploy/deploy.sh
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/vhosts/hazri.pk/httpdocs}"
PHP="${PHP:-php}"            # Plesk: /opt/plesk/php/8.4/bin/php
COMPOSER="${COMPOSER:-composer}"
cd "$APP_DIR"

$PHP artisan down --retry=30 || true
trap '$PHP artisan up' EXIT

git pull --ff-only
$COMPOSER install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

$PHP artisan migrate --force
$PHP artisan tenants:migrate --force
$PHP artisan tenants:run hazri:permissions:sync   # new permissions reach every company
$PHP artisan hazri:ai:grant-readonly              # no-op when AI_DB_READONLY_USERNAME is unset

$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
$PHP artisan filament:assets       # panel CSS/JS into public/ (the panel is unstyled without them)
$PHP artisan filament:optimize
$PHP artisan storage:link || true
echo "Deployed $(git rev-parse --short HEAD)"
