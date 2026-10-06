#!/usr/bin/env bash
set -euo pipefail
umask 077
root=/home/c/ck85651/norocel
test "$(pwd -P)" = "$root"
test "$(readlink -f public_html)" = "$root/backend/public"
archive="${1:?Archive required}"
expected="${2:?SHA-256 required}"
case "$archive" in norocel-branding-*.zip) ;; *) exit 1 ;; esac
test "$archive" = "$(basename "$archive")"
printf '%s  %s\n' "$expected" "$archive" | sha256sum -c -
unzip -tq "$archive"
backup="$root/.deploy/branding-${expected:0:16}"
test ! -e "$backup"
mkdir -p "$backup/release"
unzip -q "$archive" -d "$backup/release"
cd "$backup/release"
sha256sum -c branding-files.sha256 > "$backup/files-verified.txt"
cd "$root"
while read -r digest relative; do
  [[ "$relative" =~ ^[a-zA-Z0-9_./-]+$ && "$relative" != *..* ]]
  case "$relative" in
    backend/public/build/index.html|backend/public/build/assets/*|backend/public/icons/*|backend/public/favicon.ico|backend/resources/pwa/sw.js|backend/resources/pwa/manifest.webmanifest|backend/public/site/site.css|backend/public/site/favicon.svg|backend/public/img/norocel/social-preview.png|public-site/public/icons/*|public-site/public/favicon.ico|public-site/public/site/site.css|public-site/public/site/favicon.svg|public-site/public/img/norocel/social-preview.png|public-site/app/Providers/AdminPanelProvider.php|public-site/resources/views/layouts/site.blade.php|public-site/resources/views/components/preview.blade.php|public-site/resources/views/blocks/cta.blade.php) ;;
    *) exit 1 ;;
  esac
  [[ "$(readlink -m "$root/$relative")" == "$root/"* ]]
  if test -f "$relative"; then printf '%s\n' "$relative" >> "$backup/existing-files.txt"; else printf '%s\n' "$relative" >> "$backup/new-files.txt"; fi
done < "$backup/release/branding-files.sha256"
for relative in backend/public/sw.js backend/public/manifest.webmanifest; do
  if test -f "$relative"; then printf '%s\n' "$relative" >> "$backup/existing-files.txt"; fi
done
tar -czf "$backup/rollback.tar.gz" -T "$backup/existing-files.txt"
sha256sum backend/.env public-site/.env public-site/database/site.sqlite > "$backup/private-before.sha256"
rollback() {
  result=$?
  trap - ERR
  cd "$root"
  if test -f "$backup/new-files.txt"; then while IFS= read -r relative; do rm -f -- "$root/$relative"; done < "$backup/new-files.txt"; fi
  tar -xzf "$backup/rollback.tar.gz"
  (cd public-site && /opt/php8.5/bin/php artisan view:clear) || true
  exit "$result"
}
trap rollback ERR
while read -r digest relative; do
  mkdir -p "$(dirname "$relative")"
  cp "$backup/release/$relative" "$relative"
  chmod go-w "$relative"
done < "$backup/release/branding-files.sha256"
# Existing static copies bypass Laravel and are cached by Timeweb for a year.
for relative in backend/public/sw.js backend/public/manifest.webmanifest; do
  if test -f "$relative"; then mv "$relative" "$backup/$(basename "$relative").previous"; fi
done
(cd public-site && /opt/php8.5/bin/php artisan view:cache)
sha256sum -c "$backup/release/branding-files.sha256" > "$backup/published-files-verified.txt"
sha256sum -c "$backup/private-before.sha256" > "$backup/private-unchanged.txt"
cp "$backup/release/branding-release.json" "$root/.deploy/branding-current.json"
trap - ERR
printf 'Norocel branding published; all file hashes verified and private state unchanged\n'
