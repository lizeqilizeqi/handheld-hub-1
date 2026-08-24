#!/bin/bash
# Self-contained GCP startup: import bundle + fix images + restart (root)
set -uo pipefail

APP_DIR="/opt/handheld-hub"
COMPOSE="docker-compose.prod.yml"
LOG="/var/log/hh-import.log"
BUNDLE=""

exec >>"$LOG" 2>&1
echo "=== hh startup $(date -Is) ==="

find_bundle() {
  for f in /home/*/hh-migration-bundle.tar.gz; do
    [[ -f "$f" ]] && { echo "$f"; return 0; }
  done
  return 1
}

copy_log() {
  for home in /home/*; do
    [[ -d "$home" ]] || continue
    cp "$LOG" "$home/hh-startup.log" 2>/dev/null || true
    chown "$(basename "$home"):$(basename "$home")" "$home/hh-startup.log" 2>/dev/null || true
  done
}

fix_storage_perms() {
  local dir="$APP_DIR/storage/handhelds"
  [[ -d "$dir" ]] || return 0
  find "$dir" -type d -exec chmod 755 {} + 2>/dev/null || true
  find "$dir" -type f -exec chmod 644 {} + 2>/dev/null || true
  chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true
  echo "image files on disk: $(find "$dir" -type f ! -name '.gitkeep' | wc -l)"
}

fix_apache_images() {
  cd "$APP_DIR"
  docker compose -f "$COMPOSE" exec -T web bash -c '
if ! grep -q "Directory /var/www/html/storage/handhelds" /etc/apache2/apache2.conf; then
  cat >> /etc/apache2/apache2.conf <<EOF

<Directory /var/www/html/storage/handhelds>
    Options -Indexes
    Require all granted
</Directory>
EOF
  apache2ctl graceful
  echo "apache storage directory granted"
else
  echo "apache storage directory already ok"
fi
ls -la /var/www/html/storage/handhelds/rg-rotate/cover.webp 2>/dev/null || echo "sample image missing in container"
' 2>/dev/null || true
}

BUNDLE="$(find_bundle || true)"
if [[ -n "$BUNDLE" && ! -f "${BUNDLE}.imported" && -d "$APP_DIR" ]]; then
  WORK="/tmp/hh-import-$$"
  mkdir -p "$WORK"
  tar -xzf "$BUNDLE" -C "$WORK"
  ROOT="$WORK"
  [[ -d "$WORK/hh-migration-bundle" ]] && ROOT="$WORK/hh-migration-bundle"

  if [[ -f "$ROOT/config.local.php" && -f "$ROOT/config.secrets.php" && -f "$ROOT/database.sql" ]]; then
    cp "$ROOT/config.local.php" "$APP_DIR/config.local.php"
    cp "$ROOT/config.secrets.php" "$APP_DIR/config.secrets.php"
    chmod 644 "$APP_DIR/config.local.php" "$APP_DIR/config.secrets.php"
    mkdir -p "$APP_DIR/storage/handhelds" "$APP_DIR/storage/app/logs"
    if [[ -f "$ROOT/storage-handhelds.tar.gz" ]]; then
      tar -xzf "$ROOT/storage-handhelds.tar.gz" -C "$APP_DIR/storage/handhelds"
    fi
    fix_storage_perms

    cd "$APP_DIR"
    docker compose -f "$COMPOSE" up -d
    for i in $(seq 1 90); do
      docker compose -f "$COMPOSE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null && break
      sleep 2
    done

    SQL="$WORK/database-clean.sql"
    sed '1s/^\xEF\xBB\xBF//; /^mysqldump:/d; /^mysql: \[Warning\]/d' "$ROOT/database.sql" > "$SQL"
    docker compose -f "$COMPOSE" cp "$SQL" db:/tmp/hh-import.sql
    if docker compose -f "$COMPOSE" exec -T db sh -c 'mysql -u handheld -phandheld handheld_hub < /tmp/hh-import.sql'; then
      touch "${BUNDLE}.imported"
      echo "import ok"
    else
      echo "import failed"
    fi
    docker compose -f "$COMPOSE" exec -T web php bin/migrate.php 2>/dev/null || true
  fi
  rm -rf "$WORK"
elif [[ -f "${BUNDLE}.imported" ]]; then
  echo "bundle already imported, fixing images/apache only"
  fix_storage_perms
fi

if [[ -f "$APP_DIR/config.local.php" ]]; then
  sed -i 's|http://localhost:8080|http://oldman.dpdns.org|g' "$APP_DIR/config.local.php" 2>/dev/null || true
  sed -i 's|http://35.212.252.17|http://oldman.dpdns.org|g' "$APP_DIR/config.local.php" 2>/dev/null || true
fi

if command -v docker >/dev/null 2>&1 && [[ -d "$APP_DIR" ]]; then
  cd "$APP_DIR"
  docker compose -f "$COMPOSE" up -d --build
  fix_apache_images
  fix_storage_perms
fi

echo "=== hh startup end $(date -Is) ==="
copy_log
