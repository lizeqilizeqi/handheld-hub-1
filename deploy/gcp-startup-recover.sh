#!/bin/bash
APP_DIR="/opt/handheld-hub"
COMPOSE="docker-compose.prod.yml"
SITE="http://www.oldman.dpdns.org"
LOG="/var/log/hh-startup.log"
exec >>"$LOG" 2>&1
echo "=== start $(date -Is) ==="
copy_log() {
  for home in /home/*; do
    [[ -d "$home" ]] || continue
    cp "$LOG" "$home/hh-startup.log" 2>/dev/null || true
    chown "$(basename "$home"):$(basename "$home")" "$home/hh-startup.log" 2>/dev/null || true
  done
}
[[ -d "$APP_DIR" ]] || { copy_log; exit 1; }

chmod 644 "$APP_DIR/config.local.php" "$APP_DIR/config.secrets.php" 2>/dev/null || true
sed -i 's|http://localhost:8080|'"$SITE"'|g' "$APP_DIR/config.local.php" 2>/dev/null || true
sed -i 's|http://oldman.dpdns.org|'"$SITE"'|g' "$APP_DIR/config.local.php" 2>/dev/null || true
sed -i 's|http://localhost:8080|'"$SITE"'|g' "$APP_DIR/config.secrets.php" 2>/dev/null || true
sed -i 's|http://oldman.dpdns.org|'"$SITE"'|g' "$APP_DIR/config.secrets.php" 2>/dev/null || true
chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true

# public/index.php is maintained in the repo (home, legal pages, sitemap, robots.txt).

grep -q 'function hh_serve_storage_file' "$APP_DIR/lib/config.php" 2>/dev/null || cat >> "$APP_DIR/lib/config.php" <<'EOF'

function hh_serve_storage_file($relativePath)
{
    $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
    $relativePath = preg_replace('#\.\.+#', '', $relativePath);
    if ($relativePath === '') { http_response_code(404); exit('Not found'); }
    $file = hh_storage_fs() . '/' . $relativePath;
    if (!is_file($file)) { http_response_code(404); exit('Not found'); }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = array('jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif');
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
}
EOF

cd "$APP_DIR"
docker compose -f "$COMPOSE" up -d
for i in $(seq 1 120); do
  docker compose -f "$COMPOSE" exec -T db mysqladmin ping -h localhost -u handheld -phandheld --silent 2>/dev/null && { echo "mysql ok"; break; }
  sleep 3
done

docker compose -f "$COMPOSE" exec -T web bash -c '
sed -i "/Alias \/storage\/handhelds/d" /etc/apache2/apache2.conf
sed -i "/Directory \/var\/www\/html\/storage\/handhelds/,/<\/Directory>/d" /etc/apache2/apache2.conf
apache2ctl graceful
'

docker compose -f "$COMPOSE" exec -T web php -r '
$f="/var/www/html/lib/handheld_repo.php";
$c=file_get_contents($f);
$n="function hh_image_public_url(\$path)\n{\n    \$path = ltrim(str_replace(chr(92), \"/\", (string) \$path), \"/\");\n    \$path = preg_replace(\"#^storage/handhelds/#\", \"\", \$path);\n    return hh_storage_web() . \"/\" . \$path;\n}";
$c2=preg_replace("/function hh_image_public_url\s*\(\s*\\\$path\s*\)\s*\{[\s\S]*?\n\}/",$n,$c,1,$cnt);
if($cnt) file_put_contents($f,$c2);
echo $cnt?"url patched\n":"url patch fail\n";
'

echo "=== end ==="
copy_log
