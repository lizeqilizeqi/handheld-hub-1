#!/bin/bash
# Self-contained GCP startup: import bundle + restart (no GitHub dependency)
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

BUNDLE="$(find_bundle || true)"
if [[ -z "$BUNDLE" ]]; then
  echo "no bundle, skip import"
  copy_log
  exit 0
fi

if [[ -f "${BUNDLE}.imported" ]]; then
  echo "already imported: ${BUNDLE}.imported"
  copy_log
  exit 0
fi

[[ -d "$APP_DIR" ]] || { echo "missing $APP_DIR"; copy_log; exit 1; }

WORK="/tmp/hh-import-$$"
mkdir -p "$WORK"
tar -xzf "$BUNDLE" -C "$WORK"

ROOT="$WORK"
[[ -d "$WORK/hh-migration-bundle" ]] && ROOT="$WORK/hh-migration-bundle"

for f in database.sql config.local.php config.secrets.php; do
  [[ -f "$ROOT/$f" ]] || { echo "bundle missing $f"; rm -rf "$WORK"; copy_log; exit 1; }
done

echo "write configs"
cp "$ROOT/config.local.php" "$APP_DIR/config.local.php"
cp "$ROOT/config.secrets.php" "$APP_DIR/config.secrets.php"
chmod 644 "$APP_DIR/config.local.php" "$APP_DIR/config.secrets.php"

mkdir -p "$APP_DIR/storage/handhelds" "$APP_DIR/storage/app/logs"
if [[ -f "$ROOT/storage-handhelds.tar.gz" ]]; then
  echo "restore images"
  tar -xzf "$ROOT/storage-handhelds.tar.gz" -C "$APP_DIR/storage/handhelds"
fi
chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true

cd "$APP_DIR"
docker compose -f "$COMPOSE" up -d

echo "wait mysql"
for i in $(seq 1 90); do
  docker compose -f "$COMPOSE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null && break
  sleep 2
done

SQL="$WORK/database-clean.sql"
sed '1s/^\xEF\xBB\xBF//; /^mysqldump:/d; /^mysql: \[Warning\]/d' "$ROOT/database.sql" > "$SQL"

echo "sql first line: $(head -1 "$SQL")"
case "$(head -1 "$SQL")" in
  --*) echo "sql header ok" ;;
  *) echo "ERROR bad sql header"; rm -rf "$WORK"; copy_log; exit 1 ;;
esac

echo "import database via docker cp"
docker compose -f "$COMPOSE" cp "$SQL" db:/tmp/hh-import.sql
if docker compose -f "$COMPOSE" exec -T db sh -c 'mysql -u handheld -phandheld handheld_hub < /tmp/hh-import.sql'; then
  echo "import ok"
  touch "${BUNDLE}.imported"
else
  echo "import failed"
fi

docker compose -f "$COMPOSE" exec -T web php bin/migrate.php 2>/dev/null || true
chmod 644 "$APP_DIR/config.local.php" "$APP_DIR/config.secrets.php" 2>/dev/null || true
chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true
docker compose -f "$COMPOSE" up -d --build

rm -rf "$WORK"
echo "=== hh startup end $(date -Is) ==="
copy_log
