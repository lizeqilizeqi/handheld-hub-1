<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login();

require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once dirname(__DIR__) . '/lib/publish_service.php';
require_once dirname(__DIR__) . '/lib/spec_i18n.php';

$pdo = hh_pdo();
$msg = '';
$err = '';

if (empty($_SESSION['hh_publish_csrf'])) {
    $_SESSION['hh_publish_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['hh_publish_csrf'];

function hh_publish_check_csrf($csrf)
{
    $sess = isset($_SESSION['hh_publish_csrf']) ? (string) $_SESSION['hh_publish_csrf'] : '';
    return $sess !== '' && hash_equals($sess, $csrf);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';

    if (!hh_publish_check_csrf($token)) {
        $err = '会话已过期，请刷新后重试';
    } elseif ($action === 'publish' || $action === 'unpublish') {
        $batchMode = isset($_POST['batch_mode']) ? (string) $_POST['batch_mode'] : 'selected';
        $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : array();
        $singleId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($singleId > 0) {
            $ids[] = $singleId;
        }
        if ($action === 'publish' && $batchMode === 'ready') {
            $ids = hh_publish_ready_handheld_ids($pdo);
        } else {
            $ids = array_values(array_unique(array_filter($ids, function ($v) {
                return $v > 0;
            })));
        }

        if ($ids === array()) {
            $err = ($action === 'publish' && $batchMode === 'ready')
                ? '没有可发布的已翻译掌机（需未发布 + 有中英文内容 + 英文已翻译）'
                : '请先勾选要操作的掌机';
        } else {
            try {
                if ($action === 'publish') {
                    $n = hh_publish_handhelds($pdo, $ids);
                    $msg = '已发布 ' . (int) $n . ' 台到独立站（/zh/ 与 /en/ 前台可见）。';
                } else {
                    $n = hh_unpublish_handhelds($pdo, $ids);
                    $msg = '已撤回 ' . (int) $n . ' 台为草稿（前台不再显示）。';
                }
                $_SESSION['hh_publish_flash'] = $msg;
                $redirectId = count($ids) === 1 ? (int) $ids[0] : 0;
                $qs = $redirectId > 0 ? 'id=' . $redirectId : '';
                header('Location: publish.php' . ($qs !== '' ? '?' . $qs : ''), true, 302);
                exit;
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }
}

if (!empty($_SESSION['hh_publish_flash'])) {
    $msg = (string) $_SESSION['hh_publish_flash'];
    unset($_SESSION['hh_publish_flash']);
}

session_write_close();

require_once __DIR__ . '/layout.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$brand = isset($_GET['brand']) ? (string) $_GET['brand'] : '';
$q = isset($_GET['q']) ? (string) $_GET['q'] : '';
$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : 'ready';
$page = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
$perPage = 50;
$previewLocale = isset($_GET['locale']) && $_GET['locale'] === 'en' ? 'en' : 'zh';

$publishedCount = hh_handheld_count($pdo, array('status' => 'published'));
$draftCount = hh_handheld_count($pdo, array('status' => 'draft'));
$readyPublishCount = hh_publish_ready_count($pdo);

hh_admin_layout_start('publish');
?>

<?php if ($msg): ?><div class="alert alert-ok"><?php echo hh_h($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?php echo hh_h($err); ?></div><?php endif; ?>

<div class="card info-box">
  <h3><?php echo hh_h(hh_admin_t('publish_how_title')); ?></h3>
  <ul>
    <li><strong>独立站发布</strong>：将掌机状态设为「已发布」后，会出现在 <code>/zh/handhelds</code> 与 <code>/en/handhelds</code> 列表及详情页。</li>
    <li><strong>建议流程</strong>：抓取 → 翻译英文 → 在本页预览 → 发布到独立站。（Blogger 发布是另一步，见「Blogger 发布」菜单。）</li>
    <li><strong>批量发布</strong>：可勾选本页发布，或选「一键发布全部已翻译」一次发布所有未发布且英文已翻译的掌机。</li>
    <li><strong>撤回</strong>：已发布的掌机可「撤回为草稿」，前台立即不可见。</li>
  </ul>
</div>

<div class="grid-2">
  <div class="card">
    <h3><?php echo hh_h(hh_admin_t('publish_stats')); ?></h3>
    <p><?php echo hh_h(hh_admin_t('published_count')); ?>：<strong><?php echo (int) $publishedCount; ?></strong></p>
    <p><?php echo hh_h(hh_admin_t('publish_ready_count')); ?>：<strong><?php echo (int) $readyPublishCount; ?></strong></p>
    <p><?php echo hh_h(hh_admin_t('draft_count')); ?>：<strong><?php echo (int) $draftCount; ?></strong></p>
    <p class="muted"><?php echo hh_h(hh_admin_t('publish_stats_hint')); ?></p>
  </div>
  <div class="card">
    <h3><?php echo hh_h(hh_admin_t('publish_public_links')); ?></h3>
    <p><a href="/zh/handhelds" target="_blank" rel="noopener">中文列表 /zh/handhelds</a></p>
    <p><a href="/en/handhelds" target="_blank" rel="noopener">English /en/handhelds</a></p>
  </div>
</div>

<?php if ($id > 0):
  $h = hh_handheld_by_id($pdo, $id);
  if (!$h):
?>
<div class="card"><div class="alert alert-error"><?php echo hh_h(hh_admin_t('not_found')); ?></div></div>
<?php else:
  $readiness = hh_publish_readiness($pdo, $id);
  $specs = hh_handheld_specs($pdo, $id, $previewLocale);
  $zh = hh_handheld_content($pdo, $id, 'zh');
  $en = hh_handheld_content($pdo, $id, 'en');
  $content = $previewLocale === 'en' ? $en : $zh;
  if (!$content || (empty($content['title']) && empty($content['body_html']))) {
      $content = $previewLocale === 'en' ? $zh : $en;
  }
  $images = hh_handheld_images($pdo, $id);
  $detailImg = hh_handheld_detail_image($pdo, $id);
  $coverPath = $detailImg && !empty($detailImg['path']) ? $detailImg['path'] : ($images ? $images[0]['path'] : '');
  $isPublished = $h['status'] === 'published';
  $listQuery = http_build_query(array_filter(array(
      'brand' => $brand,
      'q' => $q,
      'status' => $statusFilter,
      'page' => $page > 1 ? $page : null,
  )));
?>
<div class="card">
  <div class="preview-header">
    <div>
      <h3><?php echo hh_h(hh_admin_t('publish_preview')); ?> — <?php echo hh_h($h['name_zh'] ?: $h['slug']); ?></h3>
      <p class="muted">
        <?php echo hh_h(hh_admin_t('slug')); ?>：<?php echo hh_h($h['slug']); ?>
        · <?php echo hh_h(hh_admin_t('brand')); ?>：<?php echo hh_h($h['brand']); ?>
        · <?php echo hh_h(hh_admin_t('status')); ?>：
        <span class="badge badge-<?php echo $isPublished ? 'published' : 'draft'; ?>"><?php echo hh_h(hh_admin_status_label($h['status'])); ?></span>
      </p>
    </div>
    <div class="preview-actions">
      <a class="btn btn-secondary" href="publish.php<?php echo $listQuery !== '' ? '?' . hh_h($listQuery) : ''; ?>"><?php echo hh_h(hh_admin_t('publish_back_list')); ?></a>
      <a class="btn btn-secondary" href="handheld.php?id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('edit')); ?></a>
      <a class="btn btn-secondary" href="translate.php?id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('generate_en')); ?></a>
    </div>
  </div>

  <?php if ($readiness['warnings']): ?>
  <div class="alert alert-error" style="margin-top:.75rem;">
    <?php foreach ($readiness['warnings'] as $w): ?>
    <div><?php echo hh_h($w); ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="locale-tabs" role="tablist">
    <a class="locale-tab<?php echo $previewLocale === 'zh' ? ' active' : ''; ?>" href="publish.php?id=<?php echo (int) $id; ?>&locale=zh<?php echo $listQuery !== '' ? '&' . hh_h($listQuery) : ''; ?>">中文预览</a>
    <a class="locale-tab<?php echo $previewLocale === 'en' ? ' active' : ''; ?>" href="publish.php?id=<?php echo (int) $id; ?>&locale=en<?php echo $listQuery !== '' ? '&' . hh_h($listQuery) : ''; ?>">English preview</a>
  </div>

  <div class="publish-preview-panel">
    <h4><?php echo hh_h($content['title'] ?? ($previewLocale === 'en' ? ($h['name_en'] ?: $h['name_zh']) : $h['name_zh'])); ?></h4>
    <p class="muted"><?php echo hh_h($h['brand']); ?> · <?php echo hh_h($h['release_date']); ?></p>
    <?php if ($coverPath): ?>
    <img class="publish-cover" src="<?php echo hh_h(hh_image_public_url($coverPath)); ?>" alt="">
    <?php endif; ?>
    <?php if (!empty($content['summary'])): ?>
    <p><?php echo hh_h($content['summary']); ?></p>
    <?php endif; ?>
    <div class="preview-body prose"><?php echo $content['body_html'] ?? '<p class="muted">无正文</p>'; ?></div>
    <?php if ($specs): ?>
    <h4 style="margin-top:1.25rem;"><?php echo hh_h(hh_admin_t('specs')); ?></h4>
    <table class="spec-preview-table">
      <tbody>
        <?php foreach ($specs as $k => $v): if ($v === '' || $v === '-') continue; ?>
        <tr><th><?php echo hh_h($k); ?></th><td><?php echo hh_h($v); ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="publish-action-bar">
    <?php if ($isPublished): ?>
    <p class="muted"><?php echo hh_h(hh_admin_t('publish_live_hint')); ?></p>
    <p>
      <a href="/zh/handheld/<?php echo hh_h($h['slug']); ?>" target="_blank" rel="noopener">/zh/handheld/<?php echo hh_h($h['slug']); ?></a>
      ·
      <a href="/en/handheld/<?php echo hh_h($h['slug']); ?>" target="_blank" rel="noopener">/en/handheld/<?php echo hh_h($h['slug']); ?></a>
    </p>
    <form method="post" style="display:inline;" onsubmit="return confirm('确定撤回为草稿？前台将不再显示。');">
      <input type="hidden" name="action" value="unpublish">
      <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
      <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
      <button type="submit" class="btn btn-secondary"><?php echo hh_h(hh_admin_t('publish_unpublish')); ?></button>
    </form>
    <?php else: ?>
    <p class="muted"><?php echo hh_h(hh_admin_t('publish_draft_hint')); ?></p>
    <form method="post" style="display:inline;" onsubmit="return confirm('确定发布到独立站？\n\n发布后 /zh/ 与 /en/ 前台可见。');">
      <input type="hidden" name="action" value="publish">
      <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
      <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
      <button type="submit" class="btn"<?php echo $readiness['ready'] ? '' : ' disabled title="缺少中文内容"'; ?>><?php echo hh_h(hh_admin_t('publish_to_site')); ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php else:
  $filters = array(
      'brand' => $brand,
      'q' => $q,
      'status' => $statusFilter,
      'limit' => $perPage,
      'offset' => ($page - 1) * $perPage,
  );
  $list = hh_publish_list($pdo, $filters);
  $totalFiltered = hh_publish_list_count($pdo, $filters);
  $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
  $brands = hh_brands($pdo);
?>
<div class="card">
  <form method="get" class="publish-filters">
    <div class="grid-2">
      <div>
        <label><?php echo hh_h(hh_admin_t('search')); ?></label>
        <input name="q" value="<?php echo hh_h($q); ?>" placeholder="名称、品牌、标识">
      </div>
      <div>
        <label><?php echo hh_h(hh_admin_t('brand')); ?></label>
        <select name="brand">
          <option value=""><?php echo hh_h(hh_admin_t('all_brands')); ?></option>
          <?php foreach ($brands as $b): ?>
          <option value="<?php echo hh_h($b['brand']); ?>"<?php echo $brand === $b['brand'] ? ' selected' : ''; ?>><?php echo hh_h($b['brand']); ?> (<?php echo (int) $b['c']; ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="publish-filter-row">
      <label><?php echo hh_h(hh_admin_t('status')); ?></label>
      <select name="status">
        <option value="ready"<?php echo $statusFilter === 'ready' ? ' selected' : ''; ?>>待发布（已翻译）</option>
        <option value="draft"<?php echo $statusFilter === 'draft' ? ' selected' : ''; ?>>草稿（全部未发布）</option>
        <option value="published"<?php echo $statusFilter === 'published' ? ' selected' : ''; ?>>已发布</option>
        <option value="review"<?php echo $statusFilter === 'review' ? ' selected' : ''; ?>>审核中</option>
        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>全部</option>
      </select>
      <button type="submit" class="btn btn-secondary"><?php echo hh_h(hh_admin_t('publish_filter')); ?></button>
    </div>
  </form>
</div>

<div class="card">
  <h3><?php echo hh_h(hh_admin_t('publish_list_title')); ?> <span class="muted">(<?php echo (int) $totalFiltered; ?>)</span></h3>
  <form method="post" id="publish-batch-form">
    <input type="hidden" name="csrf" value="<?php echo hh_h($csrf); ?>">
    <div class="batch-bar">
      <label class="batch-check"><input type="checkbox" id="select-all"> 全选本页</label>
      <select name="batch_mode" id="batch-mode">
        <option value="selected">发布所选（勾选下方）</option>
        <option value="ready">一键发布全部已翻译（<?php echo (int) $readyPublishCount; ?> 台）</option>
      </select>
      <button type="submit" name="action" value="publish" class="btn" onclick="return confirmBatch('publish');"><?php echo hh_h(hh_admin_t('publish_batch')); ?></button>
      <button type="submit" name="action" value="unpublish" class="btn btn-secondary" onclick="return confirmBatch('unpublish');"><?php echo hh_h(hh_admin_t('publish_batch_unpublish')); ?></button>
      <span class="muted batch-hint"><?php echo hh_h(hh_admin_t('publish_batch_hint')); ?></span>
    </div>
    <table>
      <tr>
        <th style="width:2.5rem"></th>
        <th><?php echo hh_h(hh_admin_t('name')); ?></th>
        <th><?php echo hh_h(hh_admin_t('brand')); ?></th>
        <th><?php echo hh_h(hh_admin_t('status')); ?></th>
        <th><?php echo hh_h(hh_admin_t('en_review')); ?></th>
        <th>英文</th>
        <th><?php echo hh_h(hh_admin_t('actions')); ?></th>
      </tr>
      <?php foreach ($list as $row):
        $rowPublished = $row['status'] === 'published';
      ?>
      <tr>
        <td><input type="checkbox" class="row-check" name="ids[]" value="<?php echo (int) $row['id']; ?>"></td>
        <td><?php echo hh_h($row['name_zh'] ?: $row['slug']); ?></td>
        <td><?php echo hh_h($row['brand']); ?></td>
        <td><span class="badge badge-<?php echo $rowPublished ? 'published' : 'draft'; ?>"><?php echo hh_h(hh_admin_status_label($row['status'])); ?></span></td>
        <td><?php echo hh_h(hh_admin_review_label($row['review_en'] ?? 'pending')); ?></td>
        <td><?php echo !empty($row['has_en']) ? '有' : '<span class="muted">无</span>'; ?></td>
        <td>
          <a href="publish.php?id=<?php echo (int) $row['id']; ?>&amp;<?php echo hh_h(http_build_query(array('brand' => $brand, 'q' => $q, 'status' => $statusFilter, 'page' => $page))); ?>"><?php echo hh_h(hh_admin_t('publish_preview')); ?></a>
          <?php if ($rowPublished): ?>
          · <a href="/zh/handheld/<?php echo hh_h($row['slug']); ?>" target="_blank" rel="noopener">前台</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>

  <?php if ($totalPages > 1): ?>
  <p class="publish-pagination">
    <?php if ($page > 1): ?>
    <a href="?<?php echo hh_h(http_build_query(array('brand' => $brand, 'q' => $q, 'status' => $statusFilter, 'page' => $page - 1))); ?>">← 上一页</a>
    <?php endif; ?>
    <span class="muted">第 <?php echo (int) $page; ?> / <?php echo (int) $totalPages; ?> 页</span>
    <?php if ($page < $totalPages): ?>
    <a href="?<?php echo hh_h(http_build_query(array('brand' => $brand, 'q' => $q, 'status' => $statusFilter, 'page' => $page + 1))); ?>">下一页 →</a>
    <?php endif; ?>
  </p>
  <?php endif; ?>
</div>

<script>
(function () {
  var selectAll = document.getElementById('select-all');
  var checks = document.querySelectorAll('.row-check');
  var batchMode = document.getElementById('batch-mode');
  var readyCount = <?php echo (int) $readyPublishCount; ?>;
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      checks.forEach(function (cb) { cb.checked = selectAll.checked; });
    });
  }
  window.confirmBatch = function (action) {
    var n = 0;
    if (action === 'publish' && batchMode && batchMode.value === 'ready') {
      n = readyCount;
      if (n === 0) {
        alert('没有可发布的已翻译掌机');
        return false;
      }
      return confirm('将发布全部 ' + n + ' 台已翻译掌机到独立站，确定继续？');
    }
    checks.forEach(function (cb) { if (cb.checked) n++; });
    if (n === 0) {
      alert('请先勾选要操作的掌机，或选择「一键发布全部已翻译」');
      return false;
    }
    var verb = action === 'publish' ? '发布' : '撤回为草稿';
    return confirm('将' + verb + ' ' + n + ' 台掌机，确定继续？');
  };
})();
</script>
<?php endif; ?>

<?php hh_admin_layout_end(); ?>
