<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/secrets.php';
require_once dirname(__DIR__) . '/lib/igdb_client.php';

hh_admin_require_login();

if (empty($_SESSION['hh_api_settings_csrf'])) {
    $_SESSION['hh_api_settings_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['hh_api_settings_csrf'];

$msg = '';
$err = '';
$testResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';

    if ($token === '' || !hash_equals($csrf, $token)) {
        $err = hh_admin_t('api_settings_csrf_error');
    } elseif ($action === 'save_igdb') {
        $r = hh_igdb_save_config(
            isset($_POST['client_id']) ? (string) $_POST['client_id'] : '',
            isset($_POST['client_secret']) ? (string) $_POST['client_secret'] : '',
            !empty($_POST['game_use_igdb'])
        );
        if ($r['ok']) {
            $msg = $r['message'];
        } else {
            $err = $r['message'];
        }
    } elseif ($action === 'test_igdb') {
        $clientId = isset($_POST['client_id']) ? trim((string) $_POST['client_id']) : hh_igdb_client_id();
        $clientSecret = isset($_POST['client_secret']) ? trim((string) $_POST['client_secret']) : '';
        if ($clientSecret === '') {
            $clientSecret = hh_igdb_client_secret();
        }
        $r = hh_igdb_test_connection($clientId, $clientSecret);
        if ($r['ok']) {
            $testResult = $r['message'];
        } else {
            $err = $r['message'];
        }
    } elseif ($action === 'delete_igdb') {
        $r = hh_igdb_delete_credentials();
        if ($r['ok']) {
            $msg = $r['message'];
        } else {
            $err = $r['message'];
        }
    }
}

$display = hh_igdb_config_display();
$configured = hh_igdb_configured();

require_once __DIR__ . '/layout.php';
hh_admin_layout_start('api_settings');
?>

<h1><?php echo hh_h(hh_admin_t('api_settings')); ?></h1>
<p class="muted"><?php echo hh_h(hh_admin_t('api_settings_hint')); ?></p>

<?php if ($msg !== ''): ?>
<p class="flash ok"><?php echo hh_h($msg); ?></p>
<?php endif; ?>
<?php if ($err !== ''): ?>
<p class="flash err"><?php echo hh_h($err); ?></p>
<?php endif; ?>
<?php if ($testResult !== ''): ?>
<p class="flash ok"><?php echo hh_h($testResult); ?></p>
<?php endif; ?>

<div class="card">
  <div class="api-key-bar">
    <div>
      <strong>IGDB（Twitch Developer）</strong>
      <?php if ($configured): ?>
        <span class="badge badge-published">已配置</span>
        <span class="muted">Client ID: <?php echo hh_h($display['client_id']); ?></span>
      <?php else: ?>
        <span class="badge badge-draft">未配置</span>
      <?php endif; ?>
      <?php if ($display['game_use_igdb'] && $configured): ?>
        <span class="badge badge-published" style="margin-left:.5rem;">Game 模块已启用</span>
      <?php endif; ?>
    </div>
  </div>

  <p class="muted" style="margin-top:.75rem;">
    在 <a href="https://dev.twitch.tv/console/apps" target="_blank" rel="noopener">Twitch Developer Console</a> 创建应用（Client Type 选 <strong>Confidential</strong>），将 Client ID 与 Client Secret 填入下方。密钥写入服务器 <code>config.secrets.php</code>，不会进入 Git。
  </p>

  <form method="post" class="form-grid" style="margin-top:1rem;" accept-charset="UTF-8">
    <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
    <input type="hidden" name="action" value="save_igdb">

    <label class="full">Client ID
      <input type="text" name="client_id" value="<?php echo hh_h($display['client_id']); ?>" autocomplete="off" maxlength="128" placeholder="Twitch Client ID">
    </label>

    <label class="full">Client Secret
      <input type="password" name="client_secret" value="" autocomplete="new-password" maxlength="256" placeholder="<?php echo $configured ? '留空则保留现有 Secret（' . hh_h($display['client_secret_masked']) . '）' : 'Twitch Client Secret'; ?>">
    </label>

    <label class="full" style="display:flex;align-items:center;gap:.5rem;">
      <input type="checkbox" name="game_use_igdb" value="1"<?php echo $display['game_use_igdb'] ? ' checked' : ''; ?>>
      <?php echo hh_h(hh_admin_t('api_settings_game_use_igdb')); ?>
    </label>

    <div class="full" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;">
      <button type="submit" class="btn">保存 IGDB 配置</button>
    </div>
  </form>

  <form method="post" style="display:inline;margin-top:.75rem;" accept-charset="UTF-8" onsubmit="return confirm('确定测试 IGDB 连通性？');">
    <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
    <input type="hidden" name="action" value="test_igdb">
    <input type="hidden" name="client_id" value="<?php echo hh_h($display['client_id']); ?>">
    <button type="submit" class="btn btn-secondary"<?php echo $configured ? '' : ' disabled title="请先保存 Client ID 与 Secret"'; ?>>连通性测试</button>
  </form>

  <?php if ($configured): ?>
  <form method="post" style="display:inline;margin-left:.5rem;" accept-charset="UTF-8" onsubmit="return confirm('确定删除 IGDB 密钥？Game 模块将无法使用 IGDB。');">
    <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
    <input type="hidden" name="action" value="delete_igdb">
    <button type="submit" class="btn btn-danger">删除 IGDB 密钥</button>
  </form>
  <?php endif; ?>
</div>

<div class="card info-box">
  <h3>说明</h3>
  <ul>
    <li>连通性测试会：向 Twitch 换 token → 向 IGDB 查询 3 个 NES 游戏样本。</li>
    <li>勾选「Game 模块使用 IGDB」后，后续游戏抓取/导入逻辑将读取此配置（导入功能接入中）。</li>
    <li>前台展示 IGDB 数据时请在页脚标注 <a href="https://www.igdb.com/" target="_blank" rel="noopener">IGDB</a> 来源。</li>
    <li>若保存失败，请检查服务器上 <code>config.secrets.php</code> 是否可写（见下方部署说明）。</li>
  </ul>
</div>

<?php hh_admin_layout_end(); ?>
