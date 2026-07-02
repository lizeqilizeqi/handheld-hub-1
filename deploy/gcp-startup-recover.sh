#!/bin/bash
# Recover: start Docker, wait MySQL, recreate web container (fixes images). No --build.
APP_DIR="/opt/handheld-hub"
COMPOSE="docker-compose.prod.yml"
LOG="/var/log/hh-startup.log"

exec >>"$LOG" 2>&1
echo "=== recover $(date -Is) ==="

copy_log() {
  for home in /home/*; do
    [[ -d "$home" ]] || continue
    cp "$LOG" "$home/hh-startup.log" 2>/dev/null || true
    chown "$(basename "$home"):$(basename "$home")" "$home/hh-startup.log" 2>/dev/null || true
  done
}

[[ -d "$APP_DIR" ]] || { echo "missing $APP_DIR"; copy_log; exit 1; }

chmod 644 "$APP_DIR/config.local.php" "$APP_DIR/config.secrets.php" 2>/dev/null || true
chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true

cd "$APP_DIR"
git pull origin main 2>/dev/null || true

docker compose -f "$COMPOSE" up -d

echo "wait mysql..."
for i in $(seq 1 60); do
  docker compose -f "$COMPOSE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null && { echo "mysql ok"; break; }
  sleep 3
done

# Recreate web only (picks up compose command with Apache image fix). No --build.
docker compose -f "$COMPOSE" up -d --force-recreate web

echo "images on disk: $(find "$APP_DIR/storage/handhelds" -type f ! -name '.gitkeep' 2>/dev/null | wc -l)"
docker compose -f "$COMPOSE" ps
echo "=== recover end $(date -Is) ==="
copy_log
