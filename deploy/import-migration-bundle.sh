#!/usr/bin/env bash
# Handheld Hub — import migration bundle (root; GCP startup or manual)
set -uo pipefail

APP_DIR="${APP_DIR:-/opt/handheld-hub}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
BUNDLE="${1:-}"
LOG="${HH_IMPORT_LOG:-/var/log/hh-import.log}"

log() { echo "==> [import] $*" | tee -a "$LOG"; }
die() { echo "ERROR: $*" | tee -a "$LOG" >&2; exit 1; }

: >"$LOG"

if [[ "$(id -u)" -ne 0 ]]; then
  die "must run as root"
fi

find_bundle() {
  local f
  if [[ -n "$BUNDLE" && -f "$BUNDLE" ]]; then
    echo "$BUNDLE"
    return 0
  fi
  for f in /home/*/hh-migration-bundle.tar.gz /tmp/hh-migration-bundle.tar.gz; do
    if [[ -f "$f" ]]; then
      echo "$f"
      return 0
    fi
  done
  return 1
}

copy_log_home() {
  local home
  for home in /home/*; do
    [[ -d "$home" ]] || continue
    cp "$LOG" "$home/hh-startup.log" 2>/dev/null || true
    chown "$(basename "$home"):$(basename "$home")" "$home/hh-startup.log" 2>/dev/null || true
    chmod 644 "$home/hh-startup.log" 2>/dev/null || true
  done
}
trap copy_log_home EXIT

BUNDLE="$(find_bundle || true)"
if [[ -z "$BUNDLE" ]]; then
  log "no hh-migration-bundle.tar.gz found, skip"
  exit 0
fi

if [[ -f "${BUNDLE}.imported" ]]; then
  log "already imported: ${BUNDLE}.imported"
  exit 0
fi

[[ -d "$APP_DIR" ]] || die "app dir missing: $APP_DIR"

WORK="/tmp/hh-migration-import-$$"
mkdir -p "$WORK"
trap 'copy_log_home; rm -rf "$WORK"' EXIT

log "extract $BUNDLE"
tar -xzf "$BUNDLE" -C "$WORK"

BUNDLE_ROOT="$WORK"
if [[ -d "$WORK/hh-migration-bundle" ]]; then
  BUNDLE_ROOT="$WORK/hh-migration-bundle"
fi

for req in database.sql config.local.php config.secrets.php; do
  [[ -f "${BUNDLE_ROOT}/${req}" ]] || die "bundle missing $req"
done

log "write config files"
cp "${BUNDLE_ROOT}/config.local.php" "${APP_DIR}/config.local.php"
cp "${BUNDLE_ROOT}/config.secrets.php" "${APP_DIR}/config.secrets.php"
chmod 644 "${APP_DIR}/config.local.php" "${APP_DIR}/config.secrets.php"

mkdir -p "${APP_DIR}/storage/handhelds" "${APP_DIR}/storage/app/logs"
if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${APP_DIR}/storage"
fi

if [[ -f "${BUNDLE_ROOT}/storage-handhelds.tar.gz" ]]; then
  log "restore storage/handhelds"
  tar -xzf "${BUNDLE_ROOT}/storage-handhelds.tar.gz" -C "${APP_DIR}/storage/handhelds"
  if id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data "${APP_DIR}/storage/handhelds"
  fi
fi

command -v docker >/dev/null 2>&1 || die "docker not installed"

cd "$APP_DIR"
log "start containers"
docker compose -f "$COMPOSE_FILE" up -d

log "wait for mysql"
for i in $(seq 1 90); do
  if docker compose -f "$COMPOSE_FILE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null; then
    break
  fi
  sleep 2
done

SQL_CLEAN="${WORK}/database-clean.sql"
log "sanitize database.sql (strip BOM / mysqldump noise)"
sed '1s/^\xEF\xBB\xBF//; /^mysqldump:/d; /^mysql: \[Warning\]/d' "${BUNDLE_ROOT}/database.sql" > "$SQL_CLEAN"
first_line="$(head -1 "$SQL_CLEAN" || true)"
case "$first_line" in
  --*|'/*!'*) ;;
  *) die "database.sql invalid after sanitize, first line: ${first_line}" ;;
esac

log "import database"
if ! docker compose -f "$COMPOSE_FILE" exec -T db mysql -u handheld -phandheld handheld_hub < "$SQL_CLEAN" >>"$LOG" 2>&1; then
  die "mysql import failed, see $LOG"
fi

log "run migrations"
docker compose -f "$COMPOSE_FILE" exec -T web php bin/migrate.php >>"$LOG" 2>&1 || true

chmod 644 "${APP_DIR}/config.local.php" "${APP_DIR}/config.secrets.php" 2>/dev/null || true
if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${APP_DIR}/storage" 2>/dev/null || true
fi

docker compose -f "$COMPOSE_FILE" up -d

touch "${BUNDLE}.imported"
log "done -> ${BUNDLE}.imported"
