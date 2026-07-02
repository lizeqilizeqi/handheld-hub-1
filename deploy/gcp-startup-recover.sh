#!/bin/bash
# Stable startup: git pull code fix + start Docker. No --build, no force-recreate.
APP_DIR="/opt/handheld-hub"
COMPOSE="docker-compose.prod.yml"
LOG="/var/log/hh-startup.log"
exec >>"$LOG" 2>&1
echo "=== startup $(date -Is) ==="
copy_log() {
  for home in /home/*; do
    [[ -d "$home" ]] || continue
    cp "$LOG" "$home/hh-startup.log" 2>/dev/null || true
    chown "$(basename "$home"):$(basename "$home")" "$home/hh-startup.log" 2>/dev/null || true
  done
}
[[ -d "$APP_DIR" ]] || { copy_log; exit 1; }
chmod 644 "$APP_DIR/config.local.php" "$APP_DIR/config.secrets.php" 2>/dev/null || true
chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true
cd "$APP_DIR"
git pull origin main 2>/dev/null || true
docker compose -f "$COMPOSE" up -d
for i in $(seq 1 120); do
  docker compose -f "$COMPOSE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null && { echo "mysql ok"; break; }
  sleep 3
done
copy_log
