#!/usr/bin/env bash
set -euo pipefail
umask 077
root=/home/c/ck85651/norocel
test "$(pwd -P)" = "$root"
archive="${1:?Archive filename required}"
expected="${2:?SHA-256 required}"
case "$archive" in norocel-public-site-*.zip) ;; *) exit 1 ;; esac
test "$archive" = "$(basename "$archive")"
printf '%s  %s\n' "$expected" "$archive" | sha256sum -c -
unzip -tq "$archive"
backup="$root/.deploy/public-site-${expected:0:16}"
test ! -e "$backup"
mkdir -p "$backup"
php=/opt/php8.5/bin/php
unzip -p "$archive" public-site-check.php > "$backup/check.php"
unzip -p "$archive" public-site-owner.php > "$backup/owner.php"
cd backend
"$php" "$backup/check.php" > "$backup/before.json"
"$php" "$backup/owner.php" export "$backup/owner.json"
cd "$root"
files=(backend/public/index.php backend/app/Http/Controllers/AuthController.php backend/public/build/index.html backend/resources/pwa/sw.js backend/resources/pwa/manifest.webmanifest)
if test -f backend/public/robots.txt; then files+=(backend/public/robots.txt); fi
tar -czf "$backup/rollback.tar.gz" "${files[@]}"
test ! -e public-site
cd backend
"$php" artisan down --retry=10
rollback() {
  result=$?
  trap - ERR
  cd "$root"
  tar -xzf "$backup/rollback.tar.gz"
  if test -L backend/public/site-media && test "$(readlink backend/public/site-media)" = "$root/public-site/storage/app/public"; then unlink backend/public/site-media; fi
  if test -d public-site; then mv public-site "$backup/failed-public-site"; fi
  cd backend
  "$php" artisan optimize:clear || true
  "$php" artisan config:cache || true
  "$php" artisan route:cache || true
  "$php" artisan up || true
  printf 'Public site deployment failed; application restored\n'
  exit "$result"
}
trap rollback ERR
cd "$root"
unzip -oq "$archive"
sha256sum -c public-site-files.sha256 > "$backup/files-verified.txt"
find public-site backend/public/site backend/public/img/norocel backend/public/js backend/public/css backend/public/fonts -type f -exec chmod go-w {} +
chmod go-w backend/public/index.php backend/bootstrap/application-path.php backend/app/Http/Controllers/AuthController.php backend/public/build/index.html backend/public/build/assets/* backend/public/sw.js backend/public/manifest.webmanifest backend/resources/pwa/sw.js backend/resources/pwa/manifest.webmanifest public-site-check.php public-site-owner.php public-site-files.sha256 deploy-public-site.sh
cd public-site
cp .env.example.timeweb .env
chmod 600 .env
touch database/site.sqlite
chmod 600 database/site.sqlite
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
cp -R public/img storage/app/public/
"$php" artisan key:generate --force
"$php" artisan migrate --force
"$php" artisan db:seed --force
"$php" artisan filament:assets
"$php" "$backup/owner.php" import "$backup/owner.json"
"$php" artisan config:cache
"$php" artisan route:cache
"$php" artisan view:cache
cd "$root"
test ! -e backend/public/site-media
ln -s "$root/public-site/storage/app/public" backend/public/site-media
if test -f backend/public/robots.txt; then mv backend/public/robots.txt "$backup/robots-static.txt"; fi
cd backend
"$php" artisan optimize:clear
"$php" artisan config:cache
"$php" artisan route:cache
"$php" "$backup/check.php" > "$backup/after.json"
"$php" -r '$a=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);$b=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);if($a!==$b){throw new RuntimeException("Existing app environment or financial data changed");}echo "Existing application environment, users and financial data unchanged\n";' "$backup/before.json" "$backup/after.json"
"$php" artisan up
trap - ERR
printf 'Public Norocel website deployed successfully\n'
