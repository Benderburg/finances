#!/usr/bin/env bash
set -euo pipefail
umask 077
root=/home/c/ck85651/norocel
test "$(pwd -P)" = "$root"
archive="${1:?Archive required}"
expected="${2:?SHA-256 required}"
case "$archive" in norocel-public-site-navigation-*.zip) ;; *) exit 1 ;; esac
test "$archive" = "$(basename "$archive")"
printf '%s  %s\n' "$expected" "$archive" | sha256sum -c -
unzip -tq "$archive"
backup="$root/.deploy/navigation-${expected:0:16}"
test ! -e "$backup"
mkdir "$backup"
tar -czf "$backup/rollback.tar.gz" backend/public/build/index.html backend/public/sw.js backend/public/manifest.webmanifest backend/resources/pwa/sw.js backend/resources/pwa/manifest.webmanifest
rollback() {
  result=$?
  trap - ERR
  cd "$root"
  tar -xzf "$backup/rollback.tar.gz"
  exit "$result"
}
trap rollback ERR
unzip -oq "$archive"
sha256sum -c .deploy/navigation-files.sha256 > "$backup/files-verified.txt"
chmod go-w backend/public/build/index.html backend/public/build/assets/* backend/public/sw.js backend/public/manifest.webmanifest backend/resources/pwa/sw.js backend/resources/pwa/manifest.webmanifest
trap - ERR
printf 'Application navigation and PWA updated; PHP, databases and environment unchanged\n'
