#!/usr/bin/env bash
# Legacy helper — use deploy/export-local-migration.ps1 + deploy/import-migration-bundle.sh

set -euo pipefail

echo "本地导出（PowerShell）："
echo "  .\\deploy\\export-local-migration.ps1"
echo ""
echo "上传到 GCP SSH home 目录后，启动脚本会自动导入。"
echo "或手动：sudo bash /opt/handheld-hub/deploy/import-migration-bundle.sh ~/hh-migration-bundle.tar.gz"
