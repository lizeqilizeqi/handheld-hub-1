#!/usr/bin/env bash
# Fix config readability for Docker www-data. Run on server:
#   curl -fsSL "https://raw.githubusercontent.com/lizeqilizeqi/handheld-hub-1/main/deploy/fix-permissions.sh" | sudo bash
set -euo pipefail
APP_DIR="${APP_DIR:-/opt/handheld-hub}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"

echo "==> Fix permissions under ${APP_DIR}"
chmod 644 "${APP_DIR}/config.local.php"
[[ -f "${APP_DIR}/config.secrets.php" ]] && chmod 644 "${APP_DIR}/config.secrets.php"
mkdir -p "${APP_DIR}/storage/app/logs" "${APP_DIR}/storage/handhelds"
if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${APP_DIR}/storage"
fi
chmod -R 775 "${APP_DIR}/storage/app/logs" 2>/dev/null || true

echo "==> Restart web container"
cd "$APP_DIR"
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-handheld-hub}"
docker compose -f "$COMPOSE_FILE" restart web

echo "==> Done. Open http://YOUR_IP/admin/"
