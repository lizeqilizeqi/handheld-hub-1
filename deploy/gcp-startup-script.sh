#!/bin/bash
# GCP VM startup script — fix Handheld Hub permissions and restart web (runs as root on boot).
chmod 644 /opt/handheld-hub/config.local.php 2>/dev/null || true
chmod 644 /opt/handheld-hub/config.secrets.php 2>/dev/null || true
if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data /opt/handheld-hub/storage 2>/dev/null || true
fi
if command -v docker >/dev/null 2>&1 && [[ -d /opt/handheld-hub ]]; then
  cd /opt/handheld-hub
  git pull origin main 2>/dev/null || true
  docker compose -f docker-compose.prod.yml up -d --build
fi
