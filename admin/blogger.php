<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login();

require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once dirname(__DIR__) . '/lib/secrets.php';
require_once dirname(__DIR__) . '/lib/blogger.php';
require_once dirname(__DIR__) . '/lib/blogger_runner.php';
require_once __DIR__ . '/layout.php';

$pdo = hh_pdo();
hh_blogger_batch_recover_stale_jobs($pdo);

$msg = isset($_GET['oauth']) && $_GET['oauth'] === 'ok' ? 'Blogger OAuth 已连接。' : '';
if (isset($_GET['started'])) {
    $msg = '后台 Blogger 批量任务 #' . (int) $_GET['started'] . ' 已启动，请查看下方进度。';
}
$err = isset($_GET['oauth']) && $_GET['oauth'] === 'err' ? (string) ($_GET['msg'] ?? 'OAuth 失败') : '';

if (empty($_SESSION['hh_blogger_csrf'])) {
    $_SESSION['hh_blogger_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['hh_blogger_csrf'];
session_write_close();

function hh_blogger_check_csrf($csrf)
{
    $sess = isset($_SESSION['hh_blogger_csrf']) ? (string) $_SESSION['hh_blogger_csrf'] : '';
    return $sess !== '' && hash_equals($sess, $csrf);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';

    if (!hh_blogger_check_csrf($token)) {
        $err = '会话已过期，请刷新后重试';
    } elseif ($action === 'save_blogger_config') {
        $r = hh_blogger_save_config(
            isset($_POST['client_id']) ? (string) $_POST['client_id'] : '',
            isset($_POST['client_secret']) ? (string) $_POST['client_secret'] : '',
            isset($_POST['redirect_uri']) ? (string) $_POST['redirect_uri'] : '',
            isset($_POST['blog_id']) ? (string) $_POST['blog_id'] : ''
        );
        if ($r['ok']) {
            $msg = $r['message'];
        } else {
            $err = $r['message'];
        }
    } elseif ($action === 'cancel_blogger_batch') {
        $cancelJobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
        if (hh_blogger_batch_cancel_job($pdo, $cancelJobId)) {
            $msg = '已停止 Blogger 批量发布。';
        } else {
            $err = '当前没有运行中的批量任务。';
        }
    } elseif ($action === 'blogger_mark_reset') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            $err = '无效的掌机 ID';
        } else {
            hh_blogger_mark_reset($pdo, $id);
            $msg = '已标记为「未 Blogger 发布」，可再次发布或批量选中后强制更新。';
        }
    } else {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if (!hh_blogger_oauth_configured()) {
            $err = '请先配置 Blogger OAuth（Client ID、Secret、Redirect URI、Blog ID）';
        } elseif (!hh_blogger_is_connected($pdo)) {
            $err = '请先连接 Google Blogger 账号';
        } elseif ($action === 'publish' && $id > 0) {
            try {
                $draft = !empty($_POST['as_draft']);
                $scheduled = isset($_POST['scheduled_at']) ? trim((string) $_POST['scheduled_at']) : '';
                $opts = array(
                    'draft' => $draft,
                    'labels' => array_filter(array_map('trim', explode(',', isset($_POST['labels']) ? (string) $_POST['labels'] : 'handheld,gaming'))),
                );
                if ($scheduled !== '') {
                    $opts['scheduled_at'] = date('c', strtotime($scheduled));
                }
                $posts = hh_blogger_publish_locales($pdo, $id, hh_blogger_publish_locales_default(), $opts);
                $parts = array();
                foreach ($posts as $loc => $post) {
                    $parts[] = $loc . ': ' . ($post['url'] ?? $post['id']);
                }
                $msg = 'Blogger 已发布/更新：' . implode('；', $parts);
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        } elseif ($action === 'publish_batch') {
            $batchMode = isset($_POST['batch_mode']) ? (string) $_POST['batch_mode'] : 'selected';
            $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : array();
            if ($batchMode === 'ready') {
                $ids = hh_blogger_ready_handheld_ids($pdo, 'pending');
            } elseif ($batchMode === 'ready_all') {
                $ids = hh_blogger_ready_handheld_ids($pdo, 'all');
            } else {
                $ids = array_values(array_unique(array_filter($ids, function ($v) {
                    return $v > 0;
                })));
            }
            if ($ids === array()) {
                $err = $batchMode === 'ready'
                    ? '没有待发布到 Blogger 的掌机（需已上独立站 + 英文正文）'
                    : ($batchMode === 'ready_all'
                        ? '没有可发布的掌机（需已上独立站 + 英文正文）'
                        : '请先勾选要发布的掌机，或选择一键发布');
            } else {
                try {
                    $jobId = hh_blogger_batch_queue($pdo, $ids, array(
                        'labels' => array('handheld', 'gaming'),
                    ));
                    $redirectFilter = isset($_POST['blogger_filter']) ? (string) $_POST['blogger_filter'] : 'pending';
                    header('Location: blogger.php?started=' . (int) $jobId . '&blogger=' . rawurlencode($redirectFilter), true, 302);
                    exit;
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
            }
        }
    }
}

$bloggerFilter = isset($_GET['blogger']) ? (string) $_GET['blogger'] : 'pending';
if (!in_array($bloggerFilter, array('all', 'pending', 'published'), true)) {
    $bloggerFilter = 'pending';
}

$configured = hh_blogger_oauth_configured();
$configDisplay = hh_blogger_config_display();
$connected = hh_blogger_is_connected($pdo);
$readyCount = hh_blogger_ready_count($pdo);
$pendingCount = hh_blogger_pending_count($pdo);
$publishedMarkCount = hh_blogger_published_mark_count($pdo);
$runningBatchJob = hh_blogger_batch_get_running_job($pdo);
$recentBatchJobs = hh_blogger_batch_jobs_recent($pdo, 8);

$watchJobId = 0;
if ($runningBatchJob) {
    $watchJobId = (int) $runningBatchJob['id'];
} elseif (isset($_GET['started'])) {
    $watchJobId = (int) $_GET['started'];
} elseif (isset($_GET['batch_job_id'])) {
    $watchJobId = (int) $_GET['batch_job_id'];
}
$watchBatchJob = $watchJobId > 0 ? hh_blogger_batch_job_by_id($pdo, $watchJobId) : null;

$readyIds = hh_blogger_ready_handheld_ids($pdo, $bloggerFilter);

$blogs = array();
if ($connected) {
    try {
        $blogs = hh_blogger_list_user_blogs($pdo);
    } catch (Throwable $e) {
        if ($err === '') {
            $err = $e->getMessage();
        }
    }
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$h = $id > 0 ? hh_handheld_by_id($pdo, $id) : null;
$bpEn = ($h && $id > 0) ? hh_blogger_post_row($pdo, $id, 'en') : null;
$enContent = ($h && $id > 0) ? hh_handheld_content($pdo, $id, 'en') : null;

$readyWhere = hh_blogger_list_where_sql($bloggerFilter);
$listSql = 'SELECT h.*,
    ce.review_status AS review_en
    FROM hh_handhelds h
    INNER JOIN hh_handheld_content ce ON ce.handheld_id = h.id AND ce.locale = \'en\'
    WHERE ' . implode(' AND ', $readyWhere) . '
    ORDER BY h.blogger_mark ASC, h.release_date DESC, h.id DESC
    LIMIT 100';
$list = $pdo->query($listSql)->fetchAll(PDO::FETCH_ASSOC);

hh_admin_layout_start('blogger');
?>

<?php if ($msg): ?><div class="alert alert-ok"><?php echo hh_h($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?php echo hh_h($err); ?></div><?php endif; ?>

<?php if ($watchBatchJob):
  $total = max(1, (int) $watchBatchJob['total_count']);
  $current = (int) $watchBatchJob['current_index'];
  $pct = $watchBatchJob['status'] === 'done' ? 100 : min(100, (int) round($current / $total * 100));
?>
<div class="card scrape-running" id="blogger-batch-monitor" data-job-id="<?php echo (int) $watchBatchJob['id']; ?>" data-csrf="<?php echo hh_h($csrf); ?>">
  <div class="scrape-monitor-head">
    <h3>Blogger 批量发布 — 任务 #<?php echo (int) $watchBatchJob['id']; ?></h3>
    <div class="scrape-monitor-actions">
      <?php if (($watchBatchJob['status'] ?? '') === 'running'): ?>
      <button type="button" class="btn btn-danger" id="btn-stop-blogger-batch">停止发布</button>
      <?php endif; ?>
      <span class="badge" id="blogger-batch-status-badge"><?php echo hh_h(hh_admin_status_label($watchBatchJob['status'])); ?></span>
    </div>
  </div>
  <div class="progress-wrap">
    <div class="progress-bar" id="blogger-batch-progress-bar" style="width:<?php echo (int) $pct; ?>%"></div>
  </div>
  <p class="progress-label" id="blogger-batch-progress-label"><?php echo (int) $pct; ?>% · <?php echo (int) $current; ?> / <?php echo (int) $watchBatchJob['total_count']; ?></p>
  <ul class="scrape-progress">
    <li>成功 <span id="blogger-batch-ok"><?php echo (int) $watchBatchJob['ok_count']; ?></span> · 失败 <span id="blogger-batch-fail"><?php echo (int) $watchBatchJob['fail_count']; ?></span></li>
    <li id="blogger-batch-message"><?php echo hh_h($watchBatchJob['message'] ?: '等待启动…'); ?></li>
  </ul>
  <div class="scrape-log-box" id="blogger-batch-log-box" aria-live="polite">
    <div class="scrape-log-empty" id="blogger-batch-log-empty">等待日志…</div>
  </div>
  <p class="muted scrape-log-hint">每 2 秒自动刷新。可关闭页面，后台会继续发布。</p>
</div>
<?php endif; ?>

<div class="card info-box">
  <h3>English Blogger 发布说明</h3>
  <ul>
    <li>Blogger 博客仅发布 <strong>英文</strong>文章；中文内容请使用独立站 <code>/zh/</code>。</li>
    <li><strong>进入列表条件</strong>：已发布到独立站 + 英文有正文。</li>
    <li><strong>图片</strong>：使用 <code>storage/handhelds</code> 本地副本，经 OAuth 上传到 Google Drive 并嵌入公开链接；<strong>不会</strong>引用掌机圈等抓取源 URL。需在 Google Cloud 启用 <strong>Google Drive API</strong>，并点击「重新连接 Google」授权 Drive。</li>
    <li>正文 HTML 中的抓取源图片与外链会被自动移除，封面图单独上传。</li>
    <li>批量发布在<strong>后台进程</strong>运行，有进度条和日志；500+ 台也不会卡死页面。按发布时间从旧到新发布，最新机型在 Blogger 首页最前。</li>
    <li>也可手动改回 <strong>未 Blogger 发布</strong>，便于区分待办与已完成。</li>
  </ul>
</div>

<div class="grid-2">
  <div class="card">
    <h3>Blogger 发布统计</h3>
    <p>可发布（已上独立站）：<strong><?php echo (int) $readyCount; ?></strong></p>
    <p>未 Blogger 发布：<strong><?php echo (int) $pendingCount; ?></strong></p>
    <p>已 Blogger 发布：<strong><?php echo (int) $publishedMarkCount; ?></strong></p>
  </div>
</div>

<div class="card api-key-bar">
  <div>
    <strong>Blogger OAuth</strong>
    <?php if ($configured): ?>
      <span class="badge badge-published">已配置</span>
      <span class="muted">Blog ID: <?php echo hh_h($configDisplay['blog_id']); ?></span>
    <?php else: ?>
      <span class="badge badge-draft">未配置</span>
      <span class="muted">填写 Google Cloud OAuth 凭据与 Blog ID 后即可连接</span>
    <?php endif; ?>
    <?php if ($connected): ?>
      <span class="badge badge-published" style="margin-left:.5rem;">已连接 Google</span>
    <?php endif; ?>
  </div>
  <button type="button" class="btn btn-secondary" id="btn-open-blogger-modal"><?php echo $configured ? '修改 OAuth 配置' : '配置 OAuth'; ?></button>
</div>

<div class="card">
  <h3>Blogger 连接</h3>
  <?php if (!$configured): ?>
    <p class="muted">点击上方「配置 OAuth」填写 Client ID、Client Secret、Redirect URI、Blog ID。</p>
    <p class="muted">Redirect URI 需与 Google Cloud Console 中授权的重定向 URI 完全一致，本地默认：<code><?php echo hh_h(hh_blogger_default_redirect_uri()); ?></code></p>
  <?php else: ?>
    <p><strong>Client ID</strong>：<code><?php echo hh_h($configDisplay['client_id']); ?></code></p>
    <p><strong>Redirect URI</strong>：<code><?php echo hh_h($configDisplay['redirect_uri']); ?></code></p>
    <p><strong>Blog ID</strong>：<code><?php echo hh_h($configDisplay['blog_id']); ?></code></p>
    <?php if (!$connected): ?>
      <?php
      try {
          $authUrl = hh_blogger_oauth_authorize_url();
          echo '<p><a class="btn" href="' . hh_h($authUrl) . '">连接 Google Blogger</a></p>';
      } catch (Throwable $e) {
          echo '<p class="alert alert-error">' . hh_h($e->getMessage()) . '</p>';
      }
      ?>
    <?php else: ?>
      <p class="alert alert-ok">OAuth 已连接，可以发布文章。</p>
      <?php
      try {
          $reconnectUrl = hh_blogger_oauth_authorize_url();
          echo '<p><a class="btn btn-secondary" href="' . hh_h($reconnectUrl) . '">重新连接 Google</a> <span class="muted">（若图片上传失败，需重新授权以开启 Google Drive 权限）</span></p>';
      } catch (Throwable $e) {
      }
      ?>
      <?php if ($blogs): ?>
      <p class="muted">账号下的博客（可用于核对 Blog ID）：</p>
      <ul>
        <?php foreach ($blogs as $b): ?>
        <li><?php echo hh_h($b['name'] ?? ''); ?> — ID: <code><?php echo hh_h($b['id'] ?? ''); ?></code></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($h): ?>
<div class="card">
  <h3>发布 — <?php echo hh_h($h['name_en'] ?: $h['name_zh']); ?></h3>
  <div>
    <p><strong>English</strong> · <?php echo trim((string) ($enContent['body_html'] ?? '')) !== '' ? '正文就绪' : '<span class="muted">缺英文正文</span>'; ?>
      · 独立站：<?php echo ($h['status'] ?? '') === 'published' ? '已发布' : '<span class="muted">未发布</span>'; ?></p>
    <?php if ($bpEn && !empty($bpEn['blogger_url'])): ?>
    <p>已有：<a href="<?php echo hh_h($bpEn['blogger_url']); ?>" target="_blank" rel="noopener"><?php echo hh_h($bpEn['blogger_url']); ?></a></p>
    <?php else: ?><p class="muted">尚未发布英文博文</p><?php endif; ?>
  </div>
  <p>Blogger 状态：<span class="badge <?php echo ($h['blogger_mark'] ?? '') === 'published' ? 'badge-published' : 'badge-draft'; ?>"><?php echo hh_h(hh_blogger_mark_label($h['blogger_mark'] ?? 'none')); ?></span></p>
  <form method="post">
    <input type="hidden" name="action" value="publish">
    <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
    <label>标签（逗号分隔）</label>
    <input name="labels" value="handheld,gaming,english,<?php echo hh_h($h['brand']); ?>">
    <label>定时发布（可选，本地时间）</label>
    <input type="datetime-local" name="scheduled_at">
    <label><input type="checkbox" name="as_draft" value="1"> 仅保存为草稿</label>
    <button type="submit"<?php echo ($configured && $connected) ? '' : ' disabled'; ?>>发布英文到 Blogger</button>
    <a class="btn btn-secondary" href="handheld.php?id=<?php echo (int) $id; ?>">编辑内容</a>
  </form>
  <?php if (($h['blogger_mark'] ?? '') === 'published'): ?>
  <form method="post" style="margin-top:.75rem;" onsubmit="return confirm('确定改回「未 Blogger 发布」？\n\n不会删除 Blogger 上的文章，仅改本地标记。');">
    <input type="hidden" name="action" value="blogger_mark_reset">
    <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
    <button type="submit" class="btn btn-secondary">标为未 Blogger 发布</button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h3>Blogger 发布列表 <span class="muted">(本页 <?php echo (int) count($list); ?> 条)</span></h3>
  <?php if ($runningBatchJob): ?>
  <p class="alert alert-error" id="blogger-running-banner">
    任务 #<?php echo (int) $runningBatchJob['id']; ?> 正在后台发布。
    <form method="post" style="display:inline;margin-left:.75rem;">
      <input type="hidden" name="action" value="cancel_blogger_batch">
      <input type="hidden" name="job_id" value="<?php echo (int) $runningBatchJob['id']; ?>">
      <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
      <button type="submit" class="btn btn-danger" style="padding:.35rem .65rem;font-size:.85rem;" onclick="return confirm('确定停止 Blogger 批量发布？\n\n已成功的不会回滚，未完成的将停止。');">停止发布</button>
    </form>
  </p>
  <?php endif; ?>
  <form method="get" class="publish-filters" style="margin-bottom:1rem;">
    <div class="publish-filter-row">
      <label>Blogger 状态</label>
      <select name="blogger">
        <option value="pending"<?php echo $bloggerFilter === 'pending' ? ' selected' : ''; ?>>未 Blogger 发布 (<?php echo (int) $pendingCount; ?>)</option>
        <option value="published"<?php echo $bloggerFilter === 'published' ? ' selected' : ''; ?>>已 Blogger 发布 (<?php echo (int) $publishedMarkCount; ?>)</option>
        <option value="all"<?php echo $bloggerFilter === 'all' ? ' selected' : ''; ?>>全部可发布 (<?php echo (int) $readyCount; ?>)</option>
      </select>
      <button type="submit" class="btn btn-secondary">筛选</button>
    </div>
  </form>
  <form method="post" id="blogger-batch-form">
    <input type="hidden" name="action" value="publish_batch">
    <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
    <input type="hidden" name="blogger_filter" value="<?php echo hh_h($bloggerFilter); ?>">
    <div class="batch-bar">
      <label class="batch-check"><input type="checkbox" id="select-all"<?php echo $runningBatchJob ? ' disabled' : ''; ?>> 全选本页</label>
      <select name="batch_mode" id="batch-mode"<?php echo $runningBatchJob ? ' disabled' : ''; ?>>
        <option value="selected">发布所选（勾选下方，可含已发布项强制更新）</option>
        <option value="ready">一键发布全部未发 Blogger（<?php echo (int) $pendingCount; ?> 台）</option>
        <option value="ready_all">一键发布全部可发布（<?php echo (int) $readyCount; ?> 台，含已发布）</option>
      </select>
      <button type="submit" class="btn" id="btn-batch-blogger"<?php echo ($configured && $connected && $readyCount > 0 && !$runningBatchJob) ? '' : ' disabled'; ?>>一键批量发布（英文）</button>
      <span class="muted batch-hint">后台发布，不卡页面；按发布时间从旧到新（最新机型在 Blogger 首页最前）</span>
    </div>
    <table>
      <tr><th style="width:2.5rem"></th><th>名称（EN）</th><th>品牌</th><th>独立站</th><th>Blogger 状态</th><th>Blogger 英文</th><th></th></tr>
      <?php foreach ($list as $row):
        $bpE = hh_blogger_post_row($pdo, (int) $row['id'], 'en');
        $isPending = ($row['blogger_mark'] ?? 'none') !== 'published';
        $checked = $isPending && $bloggerFilter === 'pending';
      ?>
      <tr>
        <td><input type="checkbox" class="row-check" name="ids[]" value="<?php echo (int) $row['id']; ?>"<?php echo $checked ? ' checked' : ''; ?><?php echo $runningBatchJob ? ' disabled' : ''; ?>></td>
        <td><?php echo hh_h($row['name_en'] ?: $row['name_zh'] ?: $row['slug']); ?></td>
        <td><?php echo hh_h($row['brand']); ?></td>
        <td><span class="badge badge-published">已发布</span></td>
        <td><span class="badge <?php echo ($row['blogger_mark'] ?? '') === 'published' ? 'badge-published' : 'badge-draft'; ?>"><?php echo hh_h(hh_blogger_mark_label($row['blogger_mark'] ?? 'none')); ?></span></td>
        <td><?php echo hh_h($bpE['sync_status'] ?? '无'); ?></td>
        <td>
          <a href="blogger.php?id=<?php echo (int) $row['id']; ?>&amp;blogger=<?php echo hh_h($bloggerFilter); ?>">发布</a>
          <?php if (($row['blogger_mark'] ?? '') === 'published'): ?>
          ·
          <form method="post" style="display:inline;" onsubmit="return confirm('改回未 Blogger 发布？');">
            <input type="hidden" name="action" value="blogger_mark_reset">
            <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
            <button type="submit" class="btn btn-secondary" style="padding:0 .25rem;font-size:inherit;">标为未发布</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>
</div>

<div class="modal-overlay" id="blogger-modal" hidden>
  <div class="modal-card" role="dialog" aria-labelledby="blogger-modal-title">
    <h3 id="blogger-modal-title">配置 Blogger OAuth</h3>
    <p class="muted">在 <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console</a> 创建 OAuth 2.0 客户端（Web 应用），启用 Blogger API，并将 Redirect URI 加入授权列表。</p>
    <form method="post" id="blogger-config-form">
      <input type="hidden" name="action" value="save_blogger_config">
      <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
      <label for="client_id_input">Client ID</label>
      <input type="text" id="client_id_input" name="client_id" required maxlength="512" autocomplete="off" value="<?php echo hh_h($configDisplay['client_id']); ?>">
      <label for="client_secret_input">Client Secret</label>
      <input type="password" id="client_secret_input" name="client_secret" maxlength="512" autocomplete="off" placeholder="<?php echo $configDisplay['client_secret_masked'] !== '' ? '留空则保留已保存的 Secret' : '必填'; ?>"<?php echo $configured ? '' : ' required'; ?>>
      <?php if ($configDisplay['client_secret_masked'] !== ''): ?>
      <p class="muted">当前：<?php echo hh_h($configDisplay['client_secret_masked']); ?></p>
      <?php endif; ?>
      <label for="redirect_uri_input">Redirect URI</label>
      <input type="url" id="redirect_uri_input" name="redirect_uri" required maxlength="512" value="<?php echo hh_h($configDisplay['redirect_uri']); ?>">
      <label for="blog_id_input">Blog ID</label>
      <input type="text" id="blog_id_input" name="blog_id" required maxlength="64" autocomplete="off" value="<?php echo hh_h($configDisplay['blog_id']); ?>" placeholder="Blogger 博客数字 ID">
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="btn-close-blogger-modal">取消</button>
        <button type="submit" class="btn">确定保存</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('blogger-modal');
  var openBtn = document.getElementById('btn-open-blogger-modal');
  var closeBtn = document.getElementById('btn-close-blogger-modal');
  var batchForm = document.getElementById('blogger-batch-form');
  var batchMode = document.getElementById('batch-mode');
  var selectAll = document.getElementById('select-all');

  function openModal() {
    if (modal) modal.hidden = false;
  }
  function closeModal() {
    if (modal) modal.hidden = true;
  }
  if (openBtn) openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeModal();
    });
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.row-check').forEach(function (cb) {
        cb.checked = selectAll.checked;
      });
    });
  }

  if (batchForm) {
    batchForm.addEventListener('submit', function (e) {
      var mode = batchMode ? batchMode.value : 'selected';
      var msg = mode === 'ready'
        ? '确定一键发布全部「未 Blogger 发布」的掌机？\n\n按发布时间从旧到新依次发布英文博文（最新机型在 Blogger 首页最前）。'
        : (mode === 'ready_all'
          ? '确定一键发布全部可发布掌机（含已 Blogger 发布）？\n\n按发布时间从旧到新依次更新英文博文。'
          : '确定发布所选掌机到 Blogger（英文）？\n\n将按发布时间从旧到新依次发布。');
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  }

  var monitor = document.getElementById('blogger-batch-monitor');
  if (!monitor) return;

  var jobId = monitor.getAttribute('data-job-id');
  var csrf = monitor.getAttribute('data-csrf') || '';
  var lastId = 0;
  var logBox = document.getElementById('blogger-batch-log-box');
  var emptyEl = document.getElementById('blogger-batch-log-empty');
  var timer = null;
  var stopping = false;
  var levelClass = { fetch: 'log-fetch', ok: 'log-ok', error: 'log-error', info: 'log-info' };
  var stopBtn = document.getElementById('btn-stop-blogger-batch');

  function enableBatchControls() {
    var banner = document.getElementById('blogger-running-banner');
    if (banner) banner.remove();
    var btn = document.getElementById('btn-batch-blogger');
    if (btn) btn.disabled = false;
    if (batchMode) batchMode.disabled = false;
    if (selectAll) selectAll.disabled = false;
    document.querySelectorAll('.row-check').forEach(function (cb) { cb.disabled = false; });
  }

  function onJobStopped() {
    enableBatchControls();
    if (stopBtn) {
      stopBtn.disabled = true;
      stopBtn.textContent = '已停止';
    }
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  if (stopBtn) {
    stopBtn.addEventListener('click', function () {
      if (stopping) return;
      if (!confirm('确定停止 Blogger 批量发布？\n\n已成功的不会回滚，未完成的将停止。')) {
        return;
      }
      stopping = true;
      stopBtn.disabled = true;
      stopBtn.textContent = '正在停止…';
      fetch('blogger_live.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'cancel', job_id: parseInt(jobId, 10), csrf: csrf }),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) {
            stopping = false;
            stopBtn.disabled = false;
            stopBtn.textContent = '停止发布';
            alert(data.message || '停止失败');
            return;
          }
          poll();
          onJobStopped();
        })
        .catch(function () {
          stopping = false;
          stopBtn.disabled = false;
          stopBtn.textContent = '停止发布';
          alert('停止请求失败，请刷新页面后重试');
        });
    });
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function appendLog(row) {
    if (emptyEl) emptyEl.remove();
    var line = document.createElement('div');
    line.className = 'scrape-log-line ' + (levelClass[row.level] || 'log-info');
    var slugPart = row.slug ? '<span class="log-slug">' + esc(row.slug) + '</span> ' : '';
    line.innerHTML = '<span class="log-time">' + esc(row.time) + '</span> ' + slugPart + esc(row.message);
    logBox.appendChild(line);
    while (logBox.children.length > 300) logBox.removeChild(logBox.firstChild);
    logBox.scrollTop = logBox.scrollHeight;
  }

  function updateJob(job) {
    document.getElementById('blogger-batch-progress-bar').style.width = job.percent + '%';
    document.getElementById('blogger-batch-progress-label').textContent =
      job.percent + '% · ' + job.current_index + ' / ' + job.total_count;
    document.getElementById('blogger-batch-ok').textContent = job.ok_count;
    document.getElementById('blogger-batch-fail').textContent = job.fail_count;
    document.getElementById('blogger-batch-message').textContent = job.message || '';
    var badge = document.getElementById('blogger-batch-status-badge');
    var labels = { running: '运行中', done: '完成', failed: '失败', cancelled: '已停止' };
    var statusLabel = labels[job.status] || job.status;
    if (job.status === 'failed' && job.message && job.message.indexOf('停止') !== -1) {
      statusLabel = '已停止';
    }
    badge.textContent = statusLabel;
    badge.className = 'badge' + (job.status === 'done' ? ' badge-published' : job.status === 'failed' ? ' badge-draft' : '');
  }

  function poll() {
    fetch('blogger_live.php?job_id=' + encodeURIComponent(jobId) + '&after_id=' + lastId, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        (data.logs || []).forEach(function (row) {
          appendLog(row);
          lastId = Math.max(lastId, row.id);
        });
        updateJob(data.job);
        if (!data.running) {
          onJobStopped();
        }
      })
      .catch(function () {});
  }

  poll();
  timer = setInterval(poll, 2000);
})();
</script>

<?php hh_admin_layout_end(); ?>
