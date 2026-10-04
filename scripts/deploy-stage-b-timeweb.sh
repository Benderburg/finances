#!/usr/bin/env bash
set -euo pipefail
root="$(cd -- "$(dirname -- "$0")" && pwd -P)"
test "$root" = /home/c/ck85651/norocel
archive="${1:?archive basename required}"
expected="${2:?SHA-256 required}"
case "$archive" in norocel-stage-b-*.zip) ;; *) exit 2 ;; esac
case "$archive" in */*) exit 2 ;; esac
cd "$root"
printf '%s  %s\n' "$expected" "$archive" | sha256sum -c -
unzip -tq "$archive"
unzip -p "$archive" stage-b-check.php > stage-b-check.php
cd backend
php=/opt/php8.5/bin/php
"$php" artisan down --retry=30
"$php" ../stage-b-check.php > ../stage-b-before.json
cd "$root"
unzip -oq "$archive"
cd backend
"$php" artisan migrate --force
"$php" artisan optimize:clear
"$php" artisan config:cache
"$php" artisan route:cache
"$php" ../stage-b-check.php > ../stage-b-after.json
cmp ../stage-b-before.json ../stage-b-after.json
printf 'Existing data and .env: unchanged\n'
"$php" artisan up
"$php" artisan norocel:fx-sync
printf 'Stage B deployed\n'
