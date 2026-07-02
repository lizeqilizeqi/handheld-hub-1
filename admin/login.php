<?php

require_once __DIR__ . '/bootstrap.php';



if (hh_admin_is_logged_in()) {

    header('Location: index.php', true, 302);

    exit;

}



$err = '';

if (empty($_SESSION['hh_login_csrf'])) {

    $_SESSION['hh_login_csrf'] = bin2hex(random_bytes(16));

}

$csrf = (string) $_SESSION['hh_login_csrf'];



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';

    if (!hash_equals($csrf, $token)) {

        $err = '会话已过期，请刷新页面后重试';

    } else {

        $r = hh_admin_login(

            isset($_POST['username']) ? (string) $_POST['username'] : '',

            isset($_POST['password']) ? (string) $_POST['password'] : ''

        );

        if ($r['ok']) {

            header('Location: index.php', true, 302);

            exit;

        }

        $err = $r['message'];

    }

}

require_once __DIR__ . '/i18n.php';

?><!DOCTYPE html>

<html lang="zh-CN">

<head>

  <meta charset="utf-8">

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?php echo hh_h(hh_admin_t('login')); ?> · <?php echo hh_h(hh_admin_t('app_title')); ?></title>

  <link rel="stylesheet" href="assets/admin.css">

</head>

<body class="auth-page">

  <div class="auth-card">

    <h1><?php echo hh_h(hh_admin_t('app_title')); ?></h1>

    <p class="muted"><?php echo hh_h(hh_admin_t('admin')); ?></p>

    <?php if ($err !== ''): ?><div class="alert alert-error"><?php echo hh_h($err); ?></div><?php endif; ?>

    <form method="post">

      <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">

      <label><?php echo hh_h(hh_admin_t('username')); ?></label>

      <input name="username" type="text" required maxlength="64" autocomplete="username">

      <label><?php echo hh_h(hh_admin_t('password')); ?></label>

      <input name="password" type="password" required maxlength="128" autocomplete="current-password">

      <button type="submit"><?php echo hh_h(hh_admin_t('login')); ?></button>

    </form>

    <p class="hint"><?php echo hh_h(hh_admin_t('login_hint')); ?></p>

  </div>

</body>

</html>

