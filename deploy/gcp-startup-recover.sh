#!/bin/bash
# Stable startup: pull image fix files, start Docker, wait MySQL. No --build, no recreate.
APP_DIR="/opt/handheld-hub"
COMPOSE="docker-compose.prod.yml"
LOG="/var/log/hh-startup.log"
RAW="https://raw.githubusercontent.com/lizeqilizeqi/handheld-hub-1/main"

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
find "$APP_DIR/storage/handhelds" -type f -exec chmod 644 {} + 2>/dev/null || true

# Deploy image fix directly (do not rely on git pull)
curl -fsSL "$RAW/public/img.php" -o "$APP_DIR/public/img.php" 2>/dev/null || true
curl -fsSL "$RAW/lib/config.php" -o "$APP_DIR/lib/config.php" 2>/dev/null || true
curl -fsSL "$RAW/lib/handheld_repo.php" -o "$APP_DIR/lib/handheld_repo.php" 2>/dev/null || true
curl -fsSL "$RAW/docker-compose.prod.yml" -o "$APP_DIR/docker-compose.prod.yml" 2>/dev/null || true
git -C "$APP_DIR" pull origin main 2>/dev/null || true

echo "sample image: $(ls -la "$APP_DIR/storage/handhelds/rg-rotate/cover.webp" 2>&1)"
echo "image count: $(find "$APP_DIR/storage/handhelds" -type f ! -name '.gitkeep' 2>/dev/null | wc -l)"

cd "$APP_DIR"
docker compose -f "$COMPOSE" up -d

for i in $(seq 1 120); do
  if docker compose -f "$COMPOSE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null; then
    echo "mysql ok ($i)"
    break
  fi
  sleep 3
done

docker compose -f "$COMPOSE" ps
echo "=== startup end $(date -Is) ==="
copy_log
