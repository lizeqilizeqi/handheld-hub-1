<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login();

require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once dirname(__DIR__) . '/lib/secrets.php';
require_once dirname(__DIR__) . '/lib/deepseek.php';
require_once dirname(__DIR__) . '/lib/translate_service.php';
require_once dirname(__DIR__) . '/lib/translate_runner.php';

$pdo = hh_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$msg = '';
if (isset($_GET['started'])) {
    $startedId = (int) $_GET['started'];
    $startedJob = hh_translate_job_by_id($pdo, $startedId);
    if ($startedJob && $startedJob['status'] === 'running') {
        $msg = '批量翻译任务 #' . $startedId . ' 已启动，请查看下方进度与日志。';
    } elseif ($startedJob && $startedJob['status'] === 'done') {
        $msg = '批量翻译任务 #' . $startedId . ' 已完成。';
    }
}
$err = '';

if (empty($_SESSION['hh_translate_csrf'])) {
    $_SESSION['hh_translate_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['hh_translate_csrf'];

function hh_translate_check_csrf($csrf)
{
    $sess = isset($_SESSION['hh_translate_csrf']) ? (string) $_SESSION['hh_translate_csrf'] : '';
    return $sess !== '' && hash_equals($sess, $csrf);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : 'translate';

    if ($action === 'save_api_key') {
        $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
        if (!hh_translate_check_csrf($token)) {
            $err = '会话已过期，请刷新后重试';
        } else {
            $r = hh_deepseek_save_api_key(isset($_POST['api_key']) ? (string) $_POST['api_key'] : '');
            $msg = $r['ok'] ? $r['message'] : '';
            if (!$r['ok']) {
                $err = $r['message'];
            }
        }
    } elseif ($action === 'cancel_translate_job') {
        $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
        if (!hh_translate_check_csrf($token)) {
            $err = '会话已过期，请刷新后重试';
        } elseif (hh_translate_cancel_running_job($pdo)) {
            $msg = '已取消卡住的翻译任务，可以重新启动批量翻译。';
        } else {
            $err = '当前没有运行中的翻译任务。';
        }
    } elseif ($action === 'translate_batch') {
        $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
        if (!hh_translate_check_csrf($token)) {
            $err = '会话已过期，请刷新后重试';
        } elseif (!hh_deepseek_api_key_configured()) {
            $err = '请先配置 DeepSeek API Key';
        } else {
            $batchMode = isset($_POST['batch_mode']) ? (string) $_POST['batch_mode'] : 'selected';
            $includeDraft = !empty($_POST['include_draft']);
            $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : array();
            $ids = array_values(array_filter($ids, function ($v) { return $v > 0; }));

            if ($batchMode === 'pending') {
                $ids = hh_translate_pending_handheld_ids($pdo, $includeDraft);
            } elseif (count($ids) === 0) {
                $err = '请先勾选要翻译的掌机，或选择「翻译全部待翻译」。';
            }

            if ($err === '') {
                try {
                    $jobId = hh_translate_queue_batch($pdo, $ids);
                    header('Location: translate.php?started=' . (int) $jobId, true, 302);
                    exit;
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
            }
        }
    } else {
        $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
        if (!hh_translate_check_csrf($token)) {
            $err = '会话已过期，请刷新后重试';
        } else {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if (!hh_deepseek_api_key_configured()) {
                $err = '请先配置 DeepSeek API Key';
            } else {
                try {
                    $verified = array_filter(array_map('trim', explode("\n", isset($_POST['verified_urls']) ? (string) $_POST['verified_urls'] : '')));
                    hh_translate_handheld_to_en($pdo, $id, $verified);
                    $msg = '英文草稿已生成，请预览并人工审核后标记为「人工已通过」。';
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
            }
        }
    }
}

session_write_close();

hh_translate_recover_stale_jobs($pdo);

require_once __DIR__ . '/layout.php';

$apiConfigured = hh_deepseek_api_key_configured();
$apiMasked = hh_deepseek_api_key_masked();

$page = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
$perPage = 50;
$listBrand = isset($_GET['brand']) ? (string) $_GET['brand'] : '';
$listQ = isset($_GET['q']) ? (string) $_GET['q'] : '';
$listReview = isset($_GET['review']) ? (string) $_GET['review'] : 'pending';
$allowedReview = array('all', 'pending', 'ai_draft', 'human_approved');
if (!in_array($listReview, $allowedReview, true)) {
    $listReview = 'all';
}

$listFilters = array(
    'brand' => $listBrand,
    'q' => $listQ,
    'review' => $listReview,
    'limit' => $perPage,
    'offset' => ($page - 1) * $perPage,
);
$list = hh_translate_page_list($pdo, $listFilters);
$listTotal = hh_translate_page_count($pdo, $listFilters);
$listTotalPages = max(1, (int) ceil($listTotal / $perPage));
if ($page > $listTotalPages) {
    $page = $listTotalPages;
    $listFilters['offset'] = ($page - 1) * $perPage;
    $list = hh_translate_page_list($pdo, $listFilters);
}
$listQueryBase = array_filter(array(
    'brand' => $listBrand,
    'q' => $listQ,
    'review' => $listReview !== 'pending' ? $listReview : null,
));
$brands = hh_brands($pdo);

$pendingIds = hh_translate_pending_handheld_ids($pdo, false);
$pendingWithDraftIds = hh_translate_pending_handheld_ids($pdo, true);
$translateStats = hh_translate_review_counts($pdo);
$runningJob = hh_translate_get_running_job($pdo);
$recentJobs = hh_translate_jobs_recent($pdo, 8);

$watchJobId = 0;
if ($runningJob) {
    $watchJobId = (int) $runningJob['id'];
} elseif (isset($_GET['started'])) {
    $watchJobId = (int) $_GET['started'];
} elseif (isset($_GET['job_id'])) {
    $watchJobId = (int) $_GET['job_id'];
}
$watchJob = $watchJobId > 0 ? hh_translate_job_by_id($pdo, $watchJobId) : null;

hh_admin_layout_start('translate');
?>
<?php if ($msg): ?><div class="alert alert-ok"><?php echo hh_h($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?php echo hh_h($err); ?></div><?php endif; ?>

<?php if ($watchJob):
  $total = max(1, (int) $watchJob['total_count']);
  $current = (int) $watchJob['current_index'];
  $pct = $watchJob['status'] === 'done' ? 100 : min(100, (int) round($current / $total * 100));
?>
<div class="card scrape-running" id="translate-monitor" data-job-id="<?php echo (int) $watchJob['id']; ?>">
  <div class="scrape-monitor-head">
    <h3>批量翻译进度 — 任务 #<?php echo (int) $watchJob['id']; ?></h3>
    <span class="badge" id="translate-status-badge"><?php echo hh_h(hh_admin_status_label($watchJob['status'])); ?></span>
  </div>
  <div class="progress-wrap">
    <div class="progress-bar" id="translate-progress-bar" style="width:<?php echo (int) $pct; ?>%"></div>
  </div>
  <p class="progress-label" id="translate-progress-label"><?php echo (int) $pct; ?>% · <?php echo (int) $current; ?> / <?php echo (int) $watchJob['total_count']; ?></p>
  <ul class="scrape-progress" id="translate-progress">
    <li>成功 <span id="translate-ok"><?php echo (int) $watchJob['ok_count']; ?></span> · 失败 <span id="translate-fail"><?php echo (int) $watchJob['fail_count']; ?></span></li>
    <li id="translate-message"><?php echo hh_h($watchJob['message'] ?: '等待启动…'); ?></li>
  </ul>
  <div class="scrape-log-box" id="translate-log-box" aria-live="polite">
    <div class="scrape-log-empty" id="translate-log-empty">等待日志…</div>
  </div>
  <p class="muted scrape-log-hint">每 2 秒自动刷新。黄色=正在翻译，绿色=完成，红色=失败。</p>
</div>
<?php endif; ?>

<div class="card api-key-bar">
  <div>
    <strong>DeepSeek API</strong>
    <?php if ($apiConfigured): ?>
      <span class="badge badge-published">已配置</span>
      <span class="muted"><?php echo hh_h($apiMasked); ?></span>
    <?php else: ?>
      <span class="badge badge-draft">未配置</span>
      <span class="muted">生成英文前需要先填写 API Key</span>
    <?php endif; ?>
  </div>
  <button type="button" class="btn btn-secondary" id="btn-open-api-modal"><?php echo $apiConfigured ? '更换 API Key' : '配置 API Key'; ?></button>
</div>

<div class="card info-box">
  <h3><?php echo hh_h(hh_admin_t('translate_how_title')); ?></h3>
  <ul>
    <li><strong>使用 AI（DeepSeek）</strong>：系统把中文参数表 + 正文发给 DeepSeek，生成英文介绍与英文参数表。</li>
    <li><strong>批量翻译</strong>：在后台运行，有进度条和实时日志；每台间隔约 1.2 秒。</li>
    <li><strong>状态说明</strong>：<span class="badge badge-draft">待翻译</span> = 还没有英文；<span class="badge badge-published">已翻译（待审核）</span> = DeepSeek 已生成英文，<strong>不算</strong>待翻译；审核后可标记「人工已通过」。</li>
    <li><strong>翻译全部</strong>：下拉选「一键翻译全部待翻译」可一次处理所有待翻译项（不限当前页）。</li>
    <li><strong>建议流程</strong>：抓取 → 批量翻译 → 预览批改 → 标记「人工已通过」→ 独立站发布。</li>
  </ul>
</div>

<?php if ($id > 0):
  $h = hh_handheld_by_id($pdo, $id);
  $en = hh_handheld_content($pdo, $id, 'en');
?>
<div class="card">
  <h3><?php echo hh_h(hh_admin_t('generate_en')); ?> — <?php echo hh_h($h['name_zh']); ?></h3>
  <p><a href="preview.php?id=<?php echo (int) $id; ?>">← 先预览中文抓取内容</a></p>
  <form method="post">
    <input type="hidden" name="action" value="translate">
    <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
    <label><?php echo hh_h(hh_admin_t('verified_urls')); ?></label>
    <textarea name="verified_urls" rows="4" placeholder="https://www.anbernic.com/..."><?php
      $urls = $en && !empty($en['verified_urls']) ? json_decode($en['verified_urls'], true) : array();
      echo hh_h(is_array($urls) ? implode("\n", $urls) : '');
    ?></textarea>
    <button type="submit"<?php echo $apiConfigured ? '' : ' disabled'; ?>><?php echo hh_h(hh_admin_t('generate_en')); ?></button>
    <a class="btn btn-secondary" href="handheld.php?id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('back_edit')); ?></a>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3>翻译进度概览</h3>
  <div class="grid-2">
    <p><?php echo hh_h(hh_admin_t('pending_translate')); ?>：<strong><?php echo (int) $translateStats['pending']; ?></strong></p>
    <p><?php echo hh_h(hh_admin_t('translate_ai_draft')); ?>：<strong><?php echo (int) $translateStats['ai_draft']; ?></strong></p>
    <p><?php echo hh_h(hh_admin_t('translate_human_approved')); ?>：<strong><?php echo (int) $translateStats['human_approved']; ?></strong></p>
    <p class="muted"><?php echo hh_h(hh_admin_t('translate_stats_hint')); ?></p>
  </div>
</div>

<div class="card">
  <h3><?php echo hh_h(hh_admin_t('translate_list')); ?> <span class="muted">(<?php echo (int) $listTotal; ?>)</span></h3>
  <?php if ($runningJob): ?>
  <p class="alert alert-error" id="translate-running-banner">
    任务 #<?php echo (int) $runningJob['id']; ?> 正在运行，请等待完成后再启动。
    <form method="post" style="display:inline;margin-left:.75rem;">
      <input type="hidden" name="action" value="cancel_translate_job">
      <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
      <button type="submit" class="btn btn-secondary" style="padding:.35rem .65rem;font-size:.85rem;">取消卡住的任务</button>
    </form>
  </p>
  <?php endif; ?>

  <form method="get" class="publish-filters" style="margin-bottom:1rem;">
    <div class="grid-2">
      <div>
        <label><?php echo hh_h(hh_admin_t('search')); ?></label>
        <input name="q" value="<?php echo hh_h($listQ); ?>" placeholder="名称、品牌、标识">
      </div>
      <div>
        <label><?php echo hh_h(hh_admin_t('brand')); ?></label>
        <select name="brand">
          <option value=""><?php echo hh_h(hh_admin_t('all_brands')); ?></option>
          <?php foreach ($brands as $b): ?>
          <option value="<?php echo hh_h($b['brand']); ?>"<?php echo $listBrand === $b['brand'] ? ' selected' : ''; ?>><?php echo hh_h($b['brand']); ?> (<?php echo (int) $b['c']; ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="publish-filter-row">
      <label><?php echo hh_h(hh_admin_t('en_review')); ?></label>
      <select name="review">
        <option value="all"<?php echo $listReview === 'all' ? ' selected' : ''; ?>>全部</option>
        <option value="pending"<?php echo $listReview === 'pending' ? ' selected' : ''; ?>>待翻译</option>
        <option value="ai_draft"<?php echo $listReview === 'ai_draft' ? ' selected' : ''; ?>>已翻译（待审核）</option>
        <option value="human_approved"<?php echo $listReview === 'human_approved' ? ' selected' : ''; ?>>人工已通过</option>
      </select>
      <button type="submit" class="btn btn-secondary"><?php echo hh_h(hh_admin_t('publish_filter')); ?></button>
    </div>
  </form>

  <form method="post" id="batch-form">
    <input type="hidden" name="action" value="translate_batch">
    <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
    <div class="batch-bar">
      <label class="batch-check"><input type="checkbox" id="select-all"> 全选本页</label>
      <select name="batch_mode" id="batch-mode"<?php echo $runningJob ? ' disabled' : ''; ?>>
        <option value="selected">翻译所选（勾选下方）</option>
        <option value="pending">一键翻译全部待翻译（<?php echo count($pendingIds); ?> 台）</option>
      </select>
      <label class="batch-check"><input type="checkbox" name="include_draft" value="1"<?php echo $runningJob ? ' disabled' : ''; ?>> 包含已翻译（重新翻译）</label>
      <button type="submit" class="btn" id="btn-batch-translate"<?php echo ($apiConfigured && !$runningJob) ? '' : ' disabled'; ?>>一键批量翻译</button>
      <span class="muted batch-hint">待翻译 <?php echo count($pendingIds); ?> 台 · 已翻译 <?php echo (int) $translateStats['ai_draft']; ?> 台</span>
    </div>
    <table>
      <tr>
        <th style="width:2.5rem"></th>
        <th><?php echo hh_h(hh_admin_t('name')); ?></th>
        <th><?php echo hh_h(hh_admin_t('brand')); ?></th>
        <th><?php echo hh_h(hh_admin_t('en_review')); ?></th>
        <th><?php echo hh_h(hh_admin_t('actions')); ?></th>
      </tr>
      <?php foreach ($list as $h):
        $rs = $h['review_en'] ?? 'pending';
        if ($rs === '' || $rs === null) {
            $rs = 'pending';
        }
        $checked = in_array((int) $h['id'], $pendingIds, true);
      ?>
      <tr>
        <td><input type="checkbox" class="row-check" name="ids[]" value="<?php echo (int) $h['id']; ?>"<?php echo $checked ? ' checked' : ''; ?><?php echo $runningJob ? ' disabled' : ''; ?>></td>
        <td><?php echo hh_h($h['name_zh']); ?></td>
        <td><?php echo hh_h($h['brand']); ?></td>
        <td><?php
          $rsLabel = hh_admin_review_label($rs);
          $rsBadge = $rs === 'ai_draft' ? ' badge-published' : ($rs === 'human_approved' ? ' badge-published' : ' badge-draft');
        ?><span class="badge<?php echo $rsBadge; ?>"><?php echo hh_h($rsLabel); ?></span></td>
        <td><?php hh_admin_action_links((int) $h['id']); ?> · <a href="translate.php?id=<?php echo (int) $h['id']; ?>">翻译</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>

  <?php if ($listTotalPages > 1): ?>
  <p class="publish-pagination">
    <?php if ($page > 1): ?>
    <a href="?<?php echo hh_h(http_build_query(array_merge($listQueryBase, array('page' => $page - 1)))); ?>">← 上一页</a>
    <?php endif; ?>
    <span class="muted">第 <?php echo (int) $page; ?> / <?php echo (int) $listTotalPages; ?> 页 · 每页 <?php echo (int) $perPage; ?> 条</span>
    <?php if ($page < $listTotalPages): ?>
    <a href="?<?php echo hh_h(http_build_query(array_merge($listQueryBase, array('page' => $page + 1)))); ?>">下一页 →</a>
    <?php endif; ?>
  </p>
  <?php endif; ?>
</div>

<?php if ($recentJobs): ?>
<div class="card" id="recent-translate-jobs">
  <h3>最近批量翻译</h3>
  <table>
    <tr><th>ID</th><th>状态</th><th>进度</th><th>成功</th><th>失败</th><th>结果</th><th></th></tr>
    <?php foreach ($recentJobs as $j): ?>
    <tr data-job-id="<?php echo (int) $j['id']; ?>">
      <td><?php echo (int) $j['id']; ?></td>
      <td class="job-status"><?php echo hh_h(hh_admin_status_label($j['status'])); ?></td>
      <td class="job-progress"><?php echo (int) $j['current_index']; ?> / <?php echo (int) $j['total_count']; ?></td>
      <td class="job-ok"><?php echo (int) $j['ok_count']; ?></td>
      <td class="job-fail"><?php echo (int) $j['fail_count']; ?></td>
      <td class="job-message"><?php echo hh_h($j['message']); ?></td>
      <td><a href="translate.php?job_id=<?php echo (int) $j['id']; ?>">日志</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="modal-overlay" id="api-modal" hidden>
  <div class="modal-card" role="dialog" aria-labelledby="api-modal-title">
    <h3 id="api-modal-title">配置 DeepSeek API Key</h3>
    <p class="muted">在 <a href="https://platform.deepseek.com/" target="_blank" rel="noopener">platform.deepseek.com</a> 创建 API Key 后粘贴到下方。</p>
    <form method="post" id="api-key-form">
      <input type="hidden" name="action" value="save_api_key">
      <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
      <label for="api_key_input">API Key</label>
      <input type="password" id="api_key_input" name="api_key" required maxlength="256" autocomplete="off" placeholder="sk-...">
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="btn-close-api-modal">取消</button>
        <button type="submit" class="btn">确定保存</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('api-modal');
  var openBtn = document.getElementById('btn-open-api-modal');
  var closeBtn = document.getElementById('btn-close-api-modal');
  var input = document.getElementById('api_key_input');
  var selectAll = document.getElementById('select-all');
  var batchForm = document.getElementById('batch-form');
  var batchMode = document.getElementById('batch-mode');
  var rowChecks = document.querySelectorAll('.row-check');

  function openModal() { modal.hidden = false; input.value = ''; input.focus(); }
  function closeModal() { modal.hidden = true; }
  openBtn.addEventListener('click', openModal);
  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeModal(); });
  <?php if (!$apiConfigured): ?>openModal();<?php endif; ?>

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      rowChecks.forEach(function (cb) { if (!cb.disabled) cb.checked = selectAll.checked; });
    });
  }

  if (batchForm) {
    batchForm.addEventListener('submit', function (e) {
      var mode = batchMode ? batchMode.value : 'selected';
      var n = 0;
      if (mode === 'pending') {
        n = <?php echo (int) count($pendingIds); ?>;
        var includeDraft = batchForm.querySelector('[name="include_draft"]');
        if (includeDraft && includeDraft.checked) n = <?php echo (int) count($pendingWithDraftIds); ?>;
      } else {
        rowChecks.forEach(function (cb) { if (cb.checked) n++; });
      }
      if (n === 0) {
        e.preventDefault();
        alert('没有可翻译的掌机，请先勾选或选择「翻译全部待翻译」。');
        return;
      }
      if (!confirm('将后台翻译 ' + n + ' 台掌机，可在进度条和日志中查看。\n\n确定开始？')) {
        e.preventDefault();
      }
    });
  }

  var monitor = document.getElementById('translate-monitor');
  if (!monitor) return;

  var jobId = monitor.getAttribute('data-job-id');
  var lastId = 0;
  var logBox = document.getElementById('translate-log-box');
  var emptyEl = document.getElementById('translate-log-empty');
  var timer = null;
  var levelClass = { fetch: 'log-fetch', ok: 'log-ok', error: 'log-error', info: 'log-info' };

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

  function enableBatchForm() {
    var btn = document.getElementById('btn-batch-translate');
    if (btn) btn.disabled = false;
    if (batchMode) batchMode.disabled = false;
    var draft = batchForm && batchForm.querySelector('[name="include_draft"]');
    if (draft) draft.disabled = false;
    rowChecks.forEach(function (cb) { cb.disabled = false; });
  }

  function syncRecentJobRow(job) {
    var row = document.querySelector('#recent-translate-jobs tr[data-job-id="' + job.id + '"]');
    if (!row) return;
    var labels = { running: '运行中', done: '完成', failed: '失败' };
    var st = row.querySelector('.job-status');
    if (st) st.textContent = labels[job.status] || job.status;
    var pr = row.querySelector('.job-progress');
    if (pr) pr.textContent = job.current_index + ' / ' + job.total_count;
    var ok = row.querySelector('.job-ok');
    if (ok) ok.textContent = job.ok_count;
    var fail = row.querySelector('.job-fail');
    if (fail) fail.textContent = job.fail_count;
    var msg = row.querySelector('.job-message');
    if (msg) msg.textContent = job.message || '';
  }

  function onJobFinished(data) {
    var banner = document.getElementById('translate-running-banner');
    if (banner) banner.remove();
    enableBatchForm();
    syncRecentJobRow(data.job);
    if (timer) { clearInterval(timer); timer = null; }
  }

  function updateJob(job) {
    document.getElementById('translate-progress-bar').style.width = job.percent + '%';
    document.getElementById('translate-progress-label').textContent =
      job.percent + '% · ' + job.current_index + ' / ' + job.total_count;
    document.getElementById('translate-ok').textContent = job.ok_count;
    document.getElementById('translate-fail').textContent = job.fail_count;
    document.getElementById('translate-message').textContent = job.message || '';
    var badge = document.getElementById('translate-status-badge');
    var labels = { running: '运行中', done: '完成', failed: '失败' };
    badge.textContent = labels[job.status] || job.status;
    badge.className = 'badge' + (job.status === 'done' ? ' badge-published' : job.status === 'failed' ? ' badge-draft' : '');
    syncRecentJobRow(job);
  }

  var jobFinished = <?php echo ($watchJob && $watchJob['status'] !== 'running') ? 'true' : 'false'; ?>;

  function poll() {
    fetch('translate_live.php?job_id=' + encodeURIComponent(jobId) + '&after_id=' + lastId, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        updateJob(data.job);
        data.logs.forEach(function (row) {
          appendLog(row);
          lastId = Math.max(lastId, row.id);
        });
        if (!data.running && !jobFinished) {
          jobFinished = true;
          onJobFinished(data);
        }
      })
      .catch(function () {});
  }

  poll();
  if (!jobFinished) {
    timer = setInterval(poll, 2000);
  } else {
    enableBatchForm();
  }
})();
</script>
<?php hh_admin_layout_end(); ?>
