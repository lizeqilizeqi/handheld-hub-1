#!/bin/bash
# GCP VM startup script — import migration bundle + fix permissions + restart (root)
APP_DIR="/opt/handheld-hub"
COMPOSE_FILE="docker-compose.prod.yml"
IMPORT_SCRIPT="/tmp/hh-import-migration-bundle.sh"
RAW_IMPORT="https://raw.githubusercontent.com/lizeqilizeqi/handheld-hub-1/main/deploy/import-migration-bundle.sh"

if command -v docker >/dev/null 2>&1 && [[ -d "$APP_DIR" ]]; then
  cd "$APP_DIR"
  git pull origin main 2>/dev/null || true
fi

if [[ -f "$APP_DIR/deploy/import-migration-bundle.sh" ]]; then
  bash "$APP_DIR/deploy/import-migration-bundle.sh" || true
elif curl -fsSL "$RAW_IMPORT" -o "$IMPORT_SCRIPT" 2>/dev/null; then
  bash "$IMPORT_SCRIPT" || true
fi

chmod 644 "$APP_DIR/config.local.php" 2>/dev/null || true
chmod 644 "$APP_DIR/config.secrets.php" 2>/dev/null || true

if [[ -f "$APP_DIR/config.local.php" ]]; then
  sed -i 's|http://localhost:8080|http://oldman.dpdns.org|g' "$APP_DIR/config.local.php" 2>/dev/null || true
  sed -i 's|http://35.212.252.17|http://oldman.dpdns.org|g' "$APP_DIR/config.local.php" 2>/dev/null || true
fi

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true
fi

if command -v docker >/dev/null 2>&1 && [[ -d "$APP_DIR" ]]; then
  cd "$APP_DIR"
  docker compose -f "$COMPOSE_FILE" up -d --build
fi
