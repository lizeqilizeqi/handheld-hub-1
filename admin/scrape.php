<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login();
session_write_close();

require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once dirname(__DIR__) . '/lib/scraper/scraper_service.php';
require_once dirname(__DIR__) . '/lib/scraper/scraper_runner.php';
require_once __DIR__ . '/layout.php';

$pdo = hh_pdo();
hh_scraper_recover_stale_jobs($pdo);

$msg = isset($_GET['started']) ? '后台抓取任务 #' . (int) $_GET['started'] . ' 已启动，请查看下方实时日志。' : '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = isset($_POST['mode']) ? (string) $_POST['mode'] : 'incremental';
    $slug = isset($_POST['slug']) ? trim((string) $_POST['slug']) : '';
    try {
        $jobId = hh_scraper_queue_job($mode, $slug !== '' ? $slug : null);
        header('Location: scrape.php?started=' . (int) $jobId, true, 302);
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$runningJob = hh_scraper_get_running_job($pdo);
$jobs = hh_scrape_jobs_recent($pdo, 20);

$watchJobId = 0;
if ($runningJob) {
    $watchJobId = (int) $runningJob['id'];
} elseif (isset($_GET['started'])) {
    $watchJobId = (int) $_GET['started'];
} elseif (isset($_GET['job_id'])) {
    $watchJobId = (int) $_GET['job_id'];
}

$watchJob = $watchJobId > 0 ? hh_scrape_job_by_id($pdo, $watchJobId) : null;

hh_admin_layout_start('scrape');
?>
<?php if ($msg): ?><div class="alert alert-ok"><?php echo hh_h($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?php echo hh_h($err); ?></div><?php endif; ?>

<?php if ($watchJob): ?>
<div class="card scrape-running" id="scrape-monitor" data-job-id="<?php echo (int) $watchJob['id']; ?>">
  <div class="scrape-monitor-head">
    <h3>抓取日志 — 任务 #<?php echo (int) $watchJob['id']; ?></h3>
    <span class="badge" id="scrape-status-badge"><?php echo hh_h(hh_admin_status_label($watchJob['status'])); ?></span>
  </div>
  <ul class="scrape-progress" id="scrape-progress">
    <li>类型：<span id="scrape-type"><?php echo hh_h(hh_admin_status_label($watchJob['job_type'])); ?></span></li>
    <li>已发现：<span id="scrape-found"><?php echo (int) $watchJob['items_found']; ?></span> 台</li>
    <li>进度：<span id="scrape-current"><?php echo (int) $watchJob['current_page']; ?></span> / <span id="scrape-total"><?php echo max((int) $watchJob['items_found'], (int) $watchJob['current_page']); ?></span></li>
    <li>新增 <span id="scrape-new"><?php echo (int) $watchJob['items_new']; ?></span> · 更新 <span id="scrape-upd"><?php echo (int) $watchJob['items_updated']; ?></span> · 失败 <span id="scrape-fail"><?php echo (int) $watchJob['items_failed']; ?></span></li>
    <li id="scrape-message"><?php echo hh_h($watchJob['message'] ?: '等待启动…'); ?></li>
  </ul>
  <div class="scrape-log-box" id="scrape-log-box" aria-live="polite">
    <div class="scrape-log-empty" id="scrape-log-empty">等待日志…</div>
  </div>
  <p class="muted scrape-log-hint">每 2 秒自动刷新。绿色=新增/更新，灰色=跳过，黄色=正在抓取，红色=失败。</p>
</div>
<?php endif; ?>

<div class="card">
  <h3><?php echo hh_h(hh_admin_t('run_scrape')); ?></h3>
  <?php if ($runningJob): ?>
  <p class="alert alert-error">当前有任务 #<?php echo (int) $runningJob['id']; ?> 正在运行，请等待完成后再启动新任务。</p>
  <?php endif; ?>
  <form method="post" class="grid-2">
    <div>
      <label><?php echo hh_h(hh_admin_t('mode')); ?></label>
      <select name="mode"<?php echo $runningJob ? ' disabled' : ''; ?>>
        <option value="incremental"><?php echo hh_h(hh_admin_t('incremental')); ?></option>
        <option value="full"><?php echo hh_h(hh_admin_t('full')); ?></option>
      </select>
      <label><?php echo hh_h(hh_admin_t('single_slug')); ?></label>
      <input name="slug" placeholder="例如 rg-rotate"<?php echo $runningJob ? ' disabled' : ''; ?>>
      <button type="submit"<?php echo $runningJob ? ' disabled' : ''; ?>><?php echo hh_h(hh_admin_t('start_scrape')); ?></button>
      <p class="muted">抓取在<strong>后台进程</strong>运行，不会锁死页面。命令行：<code>docker compose exec web php bin/scrape.php --mode=incremental</code></p>
    </div>
    <div>
      <p class="muted"><?php echo hh_h(hh_admin_t('scrape_hint')); ?></p>
      <p class="muted">增量抓取会遍历掌机圈全部列表页，每台间隔约 1.2 秒，可能需要较长时间。</p>
    </div>
  </form>
</div>

<div class="card">
  <h3><?php echo hh_h(hh_admin_t('recent_jobs')); ?></h3>
  <table>
    <tr><th>ID</th><th><?php echo hh_h(hh_admin_t('job_type')); ?></th><th><?php echo hh_h(hh_admin_t('status')); ?></th><th><?php echo hh_h(hh_admin_t('new')); ?></th><th><?php echo hh_h(hh_admin_t('updated')); ?></th><th><?php echo hh_h(hh_admin_t('failed')); ?></th><th><?php echo hh_h(hh_admin_t('job_result')); ?></th><th></th></tr>
    <?php foreach ($jobs as $j): ?>
    <tr>
      <td><?php echo (int) $j['id']; ?></td>
      <td><?php echo hh_h(hh_admin_status_label($j['job_type'])); ?></td>
      <td><?php echo hh_h(hh_admin_status_label($j['status'])); ?></td>
      <td><?php echo (int) $j['items_new']; ?></td>
      <td><?php echo (int) $j['items_updated']; ?></td>
      <td><?php echo (int) $j['items_failed']; ?></td>
      <td><?php echo hh_h($j['message']); ?></td>
      <td><a href="scrape.php?job_id=<?php echo (int) $j['id']; ?>">日志</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($watchJob): ?>
<script>
(function () {
  var monitor = document.getElementById('scrape-monitor');
  if (!monitor) return;

  var jobId = monitor.getAttribute('data-job-id');
  var lastId = 0;
  var logBox = document.getElementById('scrape-log-box');
  var emptyEl = document.getElementById('scrape-log-empty');
  var timer = null;

  var levelClass = {
    fetch: 'log-fetch',
    ok: 'log-ok',
    skip: 'log-skip',
    error: 'log-error',
    info: 'log-info'
  };

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
    while (logBox.children.length > 300) {
      logBox.removeChild(logBox.firstChild);
    }
    logBox.scrollTop = logBox.scrollHeight;
  }

  function updateJob(job) {
    document.getElementById('scrape-found').textContent = job.items_found;
    document.getElementById('scrape-current').textContent = job.current_page;
    document.getElementById('scrape-total').textContent = Math.max(job.items_found, job.current_page);
    document.getElementById('scrape-new').textContent = job.items_new;
    document.getElementById('scrape-upd').textContent = job.items_updated;
    document.getElementById('scrape-fail').textContent = job.items_failed;
    document.getElementById('scrape-message').textContent = job.message || '';
    var badge = document.getElementById('scrape-status-badge');
    var labels = { running: '运行中', done: '完成', failed: '失败', pending: '待处理' };
    badge.textContent = labels[job.status] || job.status;
    badge.className = 'badge' + (job.status === 'done' ? ' badge-published' : job.status === 'failed' ? ' badge-draft' : '');
  }

  function poll() {
    fetch('scrape_live.php?job_id=' + encodeURIComponent(jobId) + '&after_id=' + lastId, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        updateJob(data.job);
        data.logs.forEach(function (row) {
          appendLog(row);
          lastId = Math.max(lastId, row.id);
        });
        if (!data.running && timer) {
          clearInterval(timer);
          timer = null;
        }
      })
      .catch(function () {});
  }

  poll();
  timer = setInterval(poll, 2000);
})();
</script>
<?php endif; ?>
<?php hh_admin_layout_end(); ?>
