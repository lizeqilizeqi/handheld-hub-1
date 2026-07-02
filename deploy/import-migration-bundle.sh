#!/usr/bin/env bash
# Handheld Hub — 导入本地迁移包（需 root，通常由 GCP 启动脚本调用）
# 用法:
#   sudo bash deploy/import-migration-bundle.sh /home/USER/hh-migration-bundle.tar.gz
#   sudo bash deploy/import-migration-bundle.sh   # 自动查找 home 目录下的包
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/handheld-hub}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
BUNDLE="${1:-}"

log() { echo "==> [import] $*"; }
die() { echo "ERROR: $*" >&2; exit 1; }

if [[ "$(id -u)" -ne 0 ]]; then
  die "请使用 root 运行（GCP 启动脚本会自动以 root 执行）"
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

BUNDLE="$(find_bundle || true)"
if [[ -z "$BUNDLE" ]]; then
  log "未找到 hh-migration-bundle.tar.gz，跳过导入"
  exit 0
fi

if [[ -f "${BUNDLE}.imported" ]]; then
  log "迁移包已导入过（${BUNDLE}.imported），跳过"
  exit 0
fi

[[ -d "$APP_DIR" ]] || die "应用目录不存在: ${APP_DIR}"

WORK="/tmp/hh-migration-import-$$"
mkdir -p "$WORK"
trap 'rm -rf "$WORK"' EXIT

log "解压: $BUNDLE"
tar -xzf "$BUNDLE" -C "$WORK"

BUNDLE_ROOT="$WORK"
if [[ -d "$WORK/hh-migration-bundle" ]]; then
  BUNDLE_ROOT="$WORK/hh-migration-bundle"
fi

for req in database.sql config.local.php config.secrets.php; do
  [[ -f "${BUNDLE_ROOT}/${req}" ]] || die "迁移包缺少 ${req}"
done

log "写入 config.local.php / config.secrets.php"
cp "${BUNDLE_ROOT}/config.local.php" "${APP_DIR}/config.local.php"
cp "${BUNDLE_ROOT}/config.secrets.php" "${APP_DIR}/config.secrets.php"
chmod 644 "${APP_DIR}/config.local.php" "${APP_DIR}/config.secrets.php"

if [[ -f "${BUNDLE_ROOT}/MANIFEST.txt" ]]; then
  log "MANIFEST:"
  sed 's/^/    /' "${BUNDLE_ROOT}/MANIFEST.txt" || true
fi

mkdir -p "${APP_DIR}/storage/handhelds" "${APP_DIR}/storage/app/logs"
if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${APP_DIR}/storage"
fi

if [[ -f "${BUNDLE_ROOT}/storage-handhelds.tar.gz" ]]; then
  log "恢复 storage/handhelds 图片"
  tar -xzf "${BUNDLE_ROOT}/storage-handhelds.tar.gz" -C "${APP_DIR}/storage/handhelds"
  if id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data "${APP_DIR}/storage/handhelds"
  fi
fi

cd "$APP_DIR"
if ! command -v docker >/dev/null 2>&1; then
  die "未安装 Docker"
fi

log "确保容器运行"
docker compose -f "$COMPOSE_FILE" up -d

log "等待 MySQL"
for i in $(seq 1 60); do
  if docker compose -f "$COMPOSE_FILE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null; then
    break
  fi
  sleep 2
done

log "导入 database.sql（覆盖现有数据）"
docker compose -f "$COMPOSE_FILE" exec -T db mysql -u handheld -phandheld handheld_hub < "${BUNDLE_ROOT}/database.sql"

log "执行未跑过的 migration"
docker compose -f "$COMPOSE_FILE" exec -T web php bin/migrate.php 2>/dev/null || true

chmod 644 "${APP_DIR}/config.local.php" "${APP_DIR}/config.secrets.php" 2>/dev/null || true
if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${APP_DIR}/storage" 2>/dev/null || true
fi

docker compose -f "$COMPOSE_FILE" up -d

touch "${BUNDLE}.imported"
log "导入完成。原包已标记: ${BUNDLE}.imported"

BASE_URL="$(grep -E "^base_url=" "${BUNDLE_ROOT}/MANIFEST.txt" 2>/dev/null | cut -d= -f2- || true)"
REDIRECT="$(grep -E "^redirect_uri=" "${BUNDLE_ROOT}/MANIFEST.txt" 2>/dev/null | cut -d= -f2- || true)"
cat <<EOF

========================================
 Handheld Hub 数据迁移完成
========================================
  站点：     ${BASE_URL:-http://YOUR_DOMAIN}/en/handhelds
  管理后台： ${BASE_URL:-http://YOUR_DOMAIN}/admin/

  若 Blogger 尚未连接，请在 Google Cloud OAuth 凭据中添加：
    ${REDIRECT:-${BASE_URL:-http://YOUR_DOMAIN}/admin/blogger_oauth.php}
========================================
EOF
