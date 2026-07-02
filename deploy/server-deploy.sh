#!/usr/bin/env bash
# Handheld Hub — GCP / Ubuntu one-shot deploy & update.
# First time (set your Git repo URL):
#   export REPO_URL="https://github.com/YOU/handheld-hub.git"
#   curl -fsSL "$REPO_URL/raw/main/deploy/server-deploy.sh" | sudo -E bash
#
# Later updates (same command, or from server):
#   sudo bash /opt/handheld-hub/deploy/server-deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/handheld-hub}"
REPO_URL="${REPO_URL:-}"
REPO_BRANCH="${REPO_BRANCH:-main}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
ENV_FILE="/etc/handheld-hub.env"

log() { echo "==> $*"; }
die() { echo "ERROR: $*" >&2; exit 1; }

if [[ "$(id -u)" -ne 0 ]]; then
  die "请使用 root 运行：sudo bash deploy/server-deploy.sh"
fi

if [[ -f "$ENV_FILE" ]]; then
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  APP_DIR="${APP_DIR:-/opt/handheld-hub}"
  REPO_URL="${REPO_URL:-}"
fi

detect_public_url() {
  local ip url
  ip="$(curl -fsS -H 'Metadata-Flavor: Google' --max-time 2 \
    http://metadata.google.internal/computeMetadata/v1/instance/network-interfaces/0/access-configs/0/external-ip 2>/dev/null || true)"
  if [[ -z "$ip" ]]; then
    ip="$(curl -fsS --max-time 5 https://ifconfig.me 2>/dev/null || true)"
  fi
  if [[ -n "$ip" ]]; then
    url="http://${ip}"
  else
    url="http://localhost"
  fi
  echo "$url"
}

ensure_swap() {
  local mem_kb swap_kb
  mem_kb="$(awk '/MemTotal/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
  swap_kb="$(awk '/SwapTotal/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
  if [[ "$mem_kb" -lt 1800000 && "$swap_kb" -lt 900000 ]]; then
    if [[ ! -f /swapfile ]]; then
      log "内存较小，创建 2G swap（e2-micro 建议）"
      fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
      chmod 600 /swapfile
      mkswap /swapfile
      swapon /swapfile
      grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    fi
  fi
}

install_docker() {
  if command -v docker >/dev/null 2>&1; then
    return 0
  fi
  log "安装 Docker"
  apt-get update -qq
  apt-get install -y -qq ca-certificates curl git
  curl -fsSL https://get.docker.com | sh
  systemctl enable docker
  systemctl start docker
}

ensure_code() {
  if [[ -d "$APP_DIR/.git" ]]; then
    log "更新代码：git pull"
    git -C "$APP_DIR" fetch origin "$REPO_BRANCH"
    git -C "$APP_DIR" reset --hard "origin/${REPO_BRANCH}"
    return 0
  fi

  if [[ -z "$REPO_URL" ]]; then
    die "首次部署请设置 Git 仓库地址，例如：
  export REPO_URL=\"https://github.com/YOU/handheld-hub.git\"
  curl -fsSL \"\$REPO_URL/raw/main/deploy/server-deploy.sh\" | sudo -E bash"
  fi

  log "克隆仓库到 ${APP_DIR}"
  mkdir -p "$(dirname "$APP_DIR")"
  git clone --branch "$REPO_BRANCH" --depth 1 "$REPO_URL" "$APP_DIR"
}

write_env_file() {
  mkdir -p "$(dirname "$ENV_FILE")"
  cat > "$ENV_FILE" <<EOF
APP_DIR=${APP_DIR}
REPO_URL=${REPO_URL}
REPO_BRANCH=${REPO_BRANCH}
EOF
  chmod 600 "$ENV_FILE"
}

ensure_config() {
  local base_url="$1"
  local cfg="${APP_DIR}/config.local.php"
  if [[ -f "$cfg" ]]; then
    log "保留已有 config.local.php"
    return 0
  fi
  log "生成 config.local.php（base_url=${base_url}）"
  cat > "$cfg" <<PHP
<?php
return array(
    'app' => array(
        'name' => 'Handheld Hub',
        'base_url' => '${base_url}',
        'default_locale' => 'en',
        'timezone' => 'UTC',
    ),
    'mysql' => array(
        'dsn' => 'mysql:host=db;port=3306;dbname=handheld_hub;charset=utf8mb4',
        'user' => 'handheld',
        'pass' => 'handheld',
    ),
    'storage' => array(
        'fs_root' => __DIR__ . '/storage/handhelds',
        'web_prefix' => '/storage/handhelds',
    ),
    'scraper' => array(
        'base_url' => 'https://zhangjiquan.com',
        'delay_ms' => 1200,
        'user_agent' => 'HandheldHubBot/1.0 (+server)',
        'max_retries' => 3,
    ),
    'deepseek' => array(
        'api_key' => '',
        'api_url' => 'https://api.deepseek.com/chat/completions',
        'model' => 'deepseek-chat',
    ),
    'blogger' => array(
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => '${base_url}/admin/blogger_oauth.php',
        'blog_id' => '',
    ),
    'admin' => array(
        'session_name' => 'HHADMINSESSID',
        'max_fail' => 5,
        'lock_seconds' => 900,
    ),
);
PHP
  chmod 640 "$cfg"
}

compose_up() {
  cd "$APP_DIR"
  export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-handheld-hub}"
  log "构建并启动 Docker（${COMPOSE_FILE}）"
  docker compose -f "$COMPOSE_FILE" up -d --build --remove-orphans
  log "等待 MySQL 就绪"
  for i in $(seq 1 60); do
    if docker compose -f "$COMPOSE_FILE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null; then
      break
    fi
    sleep 2
  done
  log "执行数据库迁移"
  docker compose -f "$COMPOSE_FILE" exec -T web php bin/migrate.php
}

print_done() {
  local base_url="$1"
  cat <<EOF

========================================
 Handheld Hub 部署完成
========================================
  站点：     ${base_url}/en/handhelds
  管理后台： ${base_url}/admin/
  默认账号： admin / password （请尽快修改）

  后续更新只需在 SSH 执行：
    sudo bash ${APP_DIR}/deploy/server-deploy.sh

  首次从本机迁移数据（可选）：
    1. 复制 config.secrets.php 到服务器 ${APP_DIR}/
    2. rsync 图片：storage/handhelds/
    3. 导入 MySQL 数据（见后台「服务器部署」说明）

  Blogger OAuth Redirect URI 请改为：
    ${base_url}/admin/blogger_oauth.php
========================================
EOF
}

main() {
  ensure_swap
  install_docker
  ensure_code
  [[ -n "$REPO_URL" ]] && write_env_file
  PUBLIC_URL="$(detect_public_url)"
  ensure_config "$PUBLIC_URL"
  mkdir -p "${APP_DIR}/storage/app/logs" "${APP_DIR}/storage/handhelds"
  chmod -R 775 "${APP_DIR}/storage/app/logs" 2>/dev/null || true
  compose_up
  print_done "$PUBLIC_URL"
}

main "$@"
