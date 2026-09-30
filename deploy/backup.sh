#!/usr/bin/env bash
# Nightly backup (spec §14.6): mysqldump of every hazri_* database + storage/, kept 14 days.
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/vhosts/hazri.pk/httpdocs}"
DEST="${DEST:-/var/backups/hazri}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M)"
# Credentials from ~/.my.cnf ([client] user/password) — never on the command line.
mkdir -p "$DEST/$STAMP"

for db in $(mysql -N -e "SHOW DATABASES LIKE 'hazri\_%'"); do
  mysqldump --single-transaction --routines --triggers "$db" | gzip > "$DEST/$STAMP/$db.sql.gz"
done
tar -czf "$DEST/$STAMP/storage.tar.gz" -C "$APP_DIR" storage/app

find "$DEST" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} +
echo "Backup $STAMP done"
