#!/bin/bash
# Enable HTTPS with Caddy (Let's Encrypt) on GCP VM. Run as root (GCP startup script).
set -euo pipefail

APP_DIR="/opt/handheld-hub"
COMPOSE="docker-compose.prod.yml"
SITE="www.oldman.dpdns.org"
BASE_URL="https://${SITE}"
LOG="/var/log/hh-https.log"

exec >>"$LOG" 2>&1
echo "=== https setup $(date -Is) ==="

copy_log() {
  for home in /home/*; do
    [[ -d "$home" ]] || continue
    cp "$LOG" "$home/hh-https.log" 2>/dev/null || true
    chown "$(basename "$home"):$(basename "$home")" "$home/hh-https.log" 2>/dev/null || true
  done
}

if ss -tlnp 2>/dev/null | grep -q ':443 '; then
  echo "WARN: port 443 already in use — check sing-box or stop conflicting service"
  ss -tlnp | grep ':443 ' || true
fi

# Docker web only on localhost:8080 (Caddy takes 80/443)
if [[ -d "$APP_DIR" ]]; then
  if grep -q '"80:80"' "$APP_DIR/$COMPOSE" 2>/dev/null; then
    sed -i 's|"80:80"|"127.0.0.1:8080:80"|g' "$APP_DIR/$COMPOSE"
  fi
  sed -i 's|http://oldman.dpdns.org|'"$BASE_URL"'|g' "$APP_DIR/config.local.php" 2>/dev/null || true
  sed -i 's|http://www.oldman.dpdns.org|'"$BASE_URL"'|g' "$APP_DIR/config.local.php" 2>/dev/null || true
  sed -i 's|http://localhost:8080|'"$BASE_URL"'|g' "$APP_DIR/config.local.php" 2>/dev/null || true
  sed -i 's|http://oldman.dpdns.org|'"$BASE_URL"'|g' "$APP_DIR/config.secrets.php" 2>/dev/null || true
  sed -i 's|http://www.oldman.dpdns.org|'"$BASE_URL"'|g' "$APP_DIR/config.secrets.php" 2>/dev/null || true
  cd "$APP_DIR"
  docker compose -f "$COMPOSE" up -d
  for i in $(seq 1 60); do
    docker compose -f "$COMPOSE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null && break
    sleep 2
  done
fi

# Install Caddy
if ! command -v caddy >/dev/null 2>&1; then
  apt-get update -qq
  apt-get install -y -qq debian-keyring debian-archive-keyring apt-transport-https curl
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
  apt-get update -qq
  apt-get install -y -qq caddy
fi

cat > /etc/caddy/Caddyfile <<EOF
${SITE} {
    reverse_proxy 127.0.0.1:8080
}
EOF

systemctl enable caddy
systemctl reload caddy || systemctl restart caddy

echo "caddy status:"
systemctl is-active caddy || true
curl -sI "http://127.0.0.1:8080/en/handhelds" | head -1 || true
echo "=== https setup end ==="
copy_log
