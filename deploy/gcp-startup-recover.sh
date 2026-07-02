#!/bin/bash
# Final stable startup: start Docker only, fix image apache conf, wait for MySQL. No --build, no recreate.
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

mkdir -p "$APP_DIR/deploy"
cat > "$APP_DIR/deploy/apache-storage.conf" <<'EOF'
<Directory /var/www/html/storage/handhelds>
    Options -Indexes
    Require all granted
</Directory>
EOF

chmod 644 "$APP_DIR/config.local.php" "$APP_DIR/config.secrets.php" 2>/dev/null || true
chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true

cd "$APP_DIR"

# Update compose to mount apache conf (safe curl, no force-recreate)
if ! grep -q 'apache-storage.conf' "$COMPOSE" 2>/dev/null; then
  curl -fsSL "https://raw.githubusercontent.com/lizeqilizeqi/handheld-hub-1/main/docker-compose.prod.yml" \
    -o "$COMPOSE" 2>/dev/null || true
fi

docker compose -f "$COMPOSE" up -d

echo "waiting for mysql (up to 6 min)..."
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
