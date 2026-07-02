<?php
require_once __DIR__ . '/bootstrap.php';
hh_admin_require_login();
require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once __DIR__ . '/layout.php';

$pdo = hh_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0 && !empty($_GET['slug'])) {
    $bySlug = hh_handheld_by_slug($pdo, (string) $_GET['slug']);
    if ($bySlug) {
        $id = (int) $bySlug['id'];
    }
}
$h = $id > 0 ? hh_handheld_by_id($pdo, $id) : null;

hh_admin_layout_start('handhelds');

if (!$h) {
    echo '<div class="alert alert-error">' . hh_h(hh_admin_t('not_found')) . '</div>';
    echo '<p><a href="handheld.php">' . hh_h(hh_admin_t('back')) . '</a></p>';
    hh_admin_layout_end();
    exit;
}

$specs = hh_handheld_specs($pdo, $id);
$zh = hh_handheld_content($pdo, $id, 'zh');
$en = hh_handheld_content($pdo, $id, 'en');
$images = hh_handheld_images($pdo, $id);
?>
<div class="card">
  <div class="preview-header">
    <div>
      <h3><?php echo hh_h(hh_admin_t('preview_title')); ?> — <?php echo hh_h($h['name_zh'] ?: $h['slug']); ?></h3>
      <p class="muted">
        <?php echo hh_h(hh_admin_t('slug')); ?>：<?php echo hh_h($h['slug']); ?>
        · <?php echo hh_h(hh_admin_t('brand')); ?>：<?php echo hh_h($h['brand']); ?>
        · <?php echo hh_h(hh_admin_t('release')); ?>：<?php echo hh_h($h['release_date']); ?>
        · <?php echo hh_h(hh_admin_t('status')); ?>：<?php echo hh_h(hh_admin_status_label($h['status'])); ?>
      </p>
      <?php if ($h['source_url']): ?>
      <p><a href="<?php echo hh_h($h['source_url']); ?>" target="_blank" rel="noopener"><?php echo hh_h(hh_admin_t('open_source')); ?></a></p>
      <?php endif; ?>
      <?php if ($h['source_scraped_at']): ?>
      <p class="muted"><?php echo hh_h(hh_admin_t('scraped_at')); ?>：<?php echo hh_h($h['source_scraped_at']); ?></p>
      <?php endif; ?>
    </div>
    <div class="preview-actions">
      <a class="btn" href="publish.php?id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('publish')); ?></a>
      <a class="btn btn-secondary" href="handheld.php?id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('edit')); ?></a>
      <a class="btn" href="translate.php?id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('generate_en')); ?></a>
      <a class="btn btn-secondary" href="handheld.php"><?php echo hh_h(hh_admin_t('back')); ?></a>
    </div>
  </div>
</div>

<?php if ($images): ?>
<div class="card">
  <h4><?php echo hh_h(hh_admin_t('images')); ?></h4>
  <div class="preview-gallery">
    <?php foreach ($images as $img): ?>
    <figure>
      <img src="<?php echo hh_h(hh_image_public_url($img['path'])); ?>" alt="<?php echo hh_h($img['alt_text_zh'] ?: $h['name_zh']); ?>">
      <?php if (!empty($img['is_cover'])): ?><figcaption><?php echo hh_h(hh_admin_t('cover')); ?>（列表封面）</figcaption><?php else: ?><figcaption>产品图（详情页）</figcaption><?php endif; ?>
    </figure>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h4><?php echo hh_h(hh_admin_t('specs')); ?></h4>
  <?php if ($specs): ?>
  <table class="spec-preview-table">
    <tbody>
      <?php foreach ($specs as $k => $v): if ($v === '' || $v === '-') continue; ?>
      <tr><th><?php echo hh_h($k); ?></th><td><?php echo hh_h($v); ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted">暂无参数数据</p>
  <?php endif; ?>
</div>

<div class="card">
  <h4><?php echo hh_h(hh_admin_t('zh_content')); ?></h4>
  <?php if ($zh && !empty($zh['title'])): ?>
  <p><strong>标题：</strong><?php echo hh_h($zh['title']); ?></p>
  <?php endif; ?>
  <?php if ($zh && !empty($zh['summary'])): ?>
  <p><strong>摘要：</strong><?php echo hh_h($zh['summary']); ?></p>
  <?php endif; ?>
  <div class="preview-body prose"><?php echo $zh['body_html'] ?? '<p class="muted">无正文</p>'; ?></div>
</div>

<?php if ($en && (!empty($en['title']) || !empty($en['body_html']))): ?>
<div class="card">
  <h4><?php echo hh_h(hh_admin_t('en_content')); ?>（<?php echo hh_h(hh_admin_review_label($en['review_status'] ?? 'pending')); ?>）</h4>
  <?php if (!empty($en['title'])): ?><p><strong>Title：</strong><?php echo hh_h($en['title']); ?></p><?php endif; ?>
  <div class="preview-body prose"><?php echo $en['body_html'] ?? ''; ?></div>
</div>
<?php endif; ?>

<?php hh_admin_layout_end(); ?>
