#!/usr/bin/env bash
# Legacy helper — prefer deploy/server-deploy.sh on the VM.
set -euo pipefail
echo "Use deploy/server-deploy.sh on the server instead."
echo "  sudo bash deploy/server-deploy.sh"
echo ""
echo "To sync files from your PC (optional):"
REMOTE="${1:-user@your-server}"
REMOTE_DIR="${2:-/opt/handheld-hub}"
echo "  rsync -avz --exclude config.local.php --exclude config.secrets.php ./ ${REMOTE}:${REMOTE_DIR}/"
echo "  rsync -avz ./storage/handhelds/ ${REMOTE}:${REMOTE_DIR}/storage/handhelds/"
