<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

define('HH_ROOT', hh_root_dir());
define('HH_STORAGE_FS', hh_storage_fs());
define('HH_STORAGE_WEB', hh_storage_web());

function hh_bootstrap()
{
    date_default_timezone_set((string) hh_config_get('app.timezone', 'UTC'));
    hh_ensure_dir(HH_STORAGE_FS);
    hh_ensure_writable_dir(hh_app_logs_dir());
}
