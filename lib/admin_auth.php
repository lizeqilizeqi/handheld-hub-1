<?php

require_once __DIR__ . '/bootstrap.php';

function hh_admin_session_name()
{
    return (string) hh_config_get('admin.session_name', 'HHADMINSESSID');
}

function hh_admin_client_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $first = trim($parts[0]);
        if ($first !== '') {
            return $first;
        }
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return (string) $_SERVER['REMOTE_ADDR'];
    }
    return '0.0.0.0';
}

function hh_admin_throttle_key($ip, $username)
{
    return hash('sha256', strtolower((string) $username) . "\0" . (string) $ip);
}

function hh_admin_session_start()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $sn = hh_admin_session_name();
    if (session_name() !== $sn) {
        session_name($sn);
    }
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}

function hh_admin_is_logged_in()
{
    return !empty($_SESSION['hh_admin']) && is_array($_SESSION['hh_admin'])
        && !empty($_SESSION['hh_admin']['id']);
}

function hh_admin_current_user()
{
    return hh_admin_is_logged_in() ? $_SESSION['hh_admin'] : null;
}

function hh_admin_check_throttle(PDO $pdo, $throttleKey)
{
    $st = $pdo->prepare(
        'SELECT failed_count, locked_until FROM hh_admin_login_throttle WHERE throttle_key = ? LIMIT 1'
    );
    $st->execute(array($throttleKey));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['locked_until'])) {
        return array('locked' => false, 'seconds' => 0, 'message' => '');
    }
    $ts = strtotime((string) $row['locked_until']);
    if ($ts === false || $ts <= time()) {
        $pdo->prepare('UPDATE hh_admin_login_throttle SET failed_count = 0, locked_until = NULL WHERE throttle_key = ?')
            ->execute(array($throttleKey));
        return array('locked' => false, 'seconds' => 0, 'message' => '');
    }
    $sec = max(1, $ts - time());
    return array(
        'locked' => true,
        'seconds' => $sec,
        'message' => 'Too many attempts. Try again in ' . ceil($sec / 60) . ' minutes.',
    );
}

function hh_admin_record_failure(PDO $pdo, $throttleKey)
{
    $now = date('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO hh_admin_login_throttle (throttle_key, failed_count, locked_until, last_attempt)
         VALUES (?, 1, NULL, ?)
         ON DUPLICATE KEY UPDATE failed_count = failed_count + 1, last_attempt = VALUES(last_attempt)'
    )->execute(array($throttleKey, $now));

    $st = $pdo->prepare('SELECT failed_count FROM hh_admin_login_throttle WHERE throttle_key = ?');
    $st->execute(array($throttleKey));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $fc = $row ? (int) $row['failed_count'] : 0;
    $max = (int) hh_config_get('admin.max_fail', 5);
    if ($fc >= $max) {
        $lock = (int) hh_config_get('admin.lock_seconds', 900);
        $until = date('Y-m-d H:i:s', time() + $lock);
        $pdo->prepare('UPDATE hh_admin_login_throttle SET locked_until = ? WHERE throttle_key = ?')
            ->execute(array($until, $throttleKey));
        return true;
    }
    return false;
}

function hh_admin_clear_throttle(PDO $pdo, $throttleKey)
{
    $pdo->prepare('DELETE FROM hh_admin_login_throttle WHERE throttle_key = ?')->execute(array($throttleKey));
}

function hh_admin_login($username, $password)
{
    $username = trim((string) $username);
    $password = (string) $password;
    $ip = hh_admin_client_ip();
    $key = hh_admin_throttle_key($ip, $username !== '' ? $username : '_');

    try {
        $pdo = hh_pdo();
    } catch (Throwable $e) {
        return array('ok' => false, 'message' => '服务暂不可用');
    }

    $chk = hh_admin_check_throttle($pdo, $key);
    if ($chk['locked']) {
        return array('ok' => false, 'message' => $chk['message']);
    }

    if ($username === '' || $password === '') {
        hh_admin_record_failure($pdo, $key);
        usleep(300000);
        return array('ok' => false, 'message' => '请输入账号和密码');
    }

    $st = $pdo->prepare(
        'SELECT id, username, password_hash FROM hh_admin_users WHERE LOWER(TRIM(username)) = LOWER(TRIM(?)) LIMIT 1'
    );
    $st->execute(array($username));
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u || empty($u['password_hash']) || !password_verify($password, (string) $u['password_hash'])) {
        hh_admin_record_failure($pdo, $key);
        usleep(400000);
        return array('ok' => false, 'message' => '账号或密码错误');
    }

    hh_admin_clear_throttle($pdo, $key);
    session_regenerate_id(true);
    $_SESSION['hh_admin'] = array(
        'id' => (int) $u['id'],
        'username' => (string) $u['username'],
        'login_at' => time(),
    );
    return array('ok' => true, 'message' => '');
}

function hh_admin_logout()
{
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], !empty($p['secure']), true);
    }
    session_destroy();
}

function hh_admin_require_login()
{
    hh_admin_session_start();
    if (!hh_admin_is_logged_in()) {
        $ret = isset($_GET['return']) ? (string) $_GET['return'] : '';
        $q = '';
        if ($ret !== '' && preg_match('#^/[a-zA-Z0-9_./-]*$#', $ret)) {
            $q = '?return=' . rawurlencode($ret);
        }
        header('Location: login.php' . $q, true, 302);
        exit;
    }
}

function hh_admin_require_login_json()
{
    hh_admin_session_start();
    if (hh_admin_is_logged_in()) {
        return;
    }
    hh_json_response(array('ok' => false, 'message' => 'Authentication required', 'code' => 'auth_required'), 401);
}
