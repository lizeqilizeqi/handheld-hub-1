<?php

require_once __DIR__ . '/config.php';

function hh_pdo()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $m = hh_config_get('mysql');
    if (!is_array($m)) {
        throw new RuntimeException('mysql config missing');
    }
    $dsn = isset($m['dsn']) ? (string) $m['dsn'] : '';
    $user = isset($m['user']) ? (string) $m['user'] : '';
    $pass = isset($m['pass']) ? (string) $m['pass'] : '';
    if ($dsn === '') {
        throw new RuntimeException('mysql.dsn empty');
    }
    $pdo = new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ));
    return $pdo;
}
