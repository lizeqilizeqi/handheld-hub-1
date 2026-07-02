#!/bin/bash
# Fix images: write minimal img.php + patch URL function + start Docker.
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

# Minimal img.php — no bootstrap (bootstrap requires writable logs dir, causes HTTP 500)
cat > "$APP_DIR/public/img.php" <<'ENDPHP'
<?php
require_once dirname(__DIR__) . '/lib/config.php';
$path = isset($_GET['f']) ? (string) $_GET['f'] : '';
$path = ltrim(str_replace('\\', '/', $path), '/');
$path = preg_replace('#\.\.+#', '', $path);
$root = hh_storage_fs();
$file = $root . '/' . $path;
if ($path === '' || !is_file($file)) {
    http_response_code(404);
    exit('not found');
}
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$types = array('jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif');
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=86400');
readfile($file);
ENDPHP

chmod 644 "$APP_DIR/public/img.php" "$APP_DIR/config.local.php" "$APP_DIR/config.secrets.php" 2>/dev/null || true
mkdir -p "$APP_DIR/storage/app/logs"
chmod -R 775 "$APP_DIR/storage/app/logs" 2>/dev/null || true
chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true

cd "$APP_DIR"
docker compose -f "$COMPOSE" up -d

for i in $(seq 1 120); do
  docker compose -f "$COMPOSE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null && { echo "mysql ok"; break; }
  sleep 3
done

# Patch image URL function inside container (reliable)
docker compose -f "$COMPOSE" exec -T web php -r '
$f = "/var/www/html/lib/handheld_repo.php";
$c = file_get_contents($f);
$new = "function hh_image_public_url(\$path)\n{\n    \$path = ltrim(str_replace(chr(92), \"/\", (string) \$path), \"/\");\n    \$path = preg_replace(\"#^storage/handhelds/#\", \"\", \$path);\n    return hh_base_url() . \"/img.php?f=\" . rawurlencode(\$path);\n}";
$c2 = preg_replace("/function hh_image_public_url\s*\(\s*\\\$path\s*\)\s*\{.*?\n\}/s", $new, $c, 1, $n);
if ($n > 0) { file_put_contents($f, $c2); echo "patched handheld_repo\n"; } else { echo "patch skipped or failed\n"; }
'

echo "images: $(find $APP_DIR/storage/handhelds -type f ! -name .gitkeep 2>/dev/null | wc -l)"
docker compose -f "$COMPOSE" exec -T web php -r 'echo is_file(hh_storage_fs()."/rg-rotate/cover.webp")?"img ok\n":"img missing\n";' 2>/dev/null || true
echo "=== end $(date -Is) ==="
copy_log
