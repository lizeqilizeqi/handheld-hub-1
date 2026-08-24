<?php
/** Redirect legacy img.php links to /storage/handhelds/ */
$path = isset($_GET['f']) ? (string) $_GET['f'] : '';
$path = ltrim(str_replace('\\', '/', $path), '/');
$path = preg_replace('#\.\.+#', '', $path);
if ($path === '') {
    http_response_code(404);
    exit('not found');
}
header('Location: /storage/handhelds/' . rawurlencode($path), true, 302);
exit;
