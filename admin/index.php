<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login();

require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once dirname(__DIR__) . '/lib/translate_service.php';
require_once dirname(__DIR__) . '/lib/site_repo.php';
require_once __DIR__ . '/layout.php';

$pdo = hh_pdo();
$total = hh_handheld_count($pdo, array());
$published = hh_handheld_count($pdo, array('status' => 'published'));
$draft = hh_handheld_count($pdo, array('status' => 'draft'));
$pendingTranslate = count(hh_translate_pending_handheld_ids($pdo, false));
$translateStats = hh_translate_review_counts($pdo);
$recent = hh_handheld_list($pdo, array('limit' => 10));
$jobs = hh_scrape_jobs_recent($pdo, 5);

$hubFlash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hub_toggle']) && hh_sites_table_exists($pdo)) {
    $codes = array('handhelds', 'game', 'news');
    foreach ($codes as $code) {
        $visible = !empty($_POST['visible_' . $code]);
        hh_hub_section_set_visible($pdo, $code, $visible);
    }
    $hubFlash = hh_admin_t('hub_modules_saved');
}

$hubModules = hh_hub_modules_visible_map($pdo);
$hubLabels = array(
    'handhelds' => hh_admin_t('hub_module_handhelds'),
    'game' => hh_admin_t('hub_module_games'),
    'news' => hh_admin_t('hub_module_news'),
);

hh_admin_layout_start('dashboard');
?>
<?php if ($hubFlash !== ''): ?><p class="flash ok"><?php echo hh_h($hubFlash); ?></p><?php endif; ?>

<div class="card hub-modules-card">
  <h3><?php echo hh_h(hh_admin_t('hub_modules')); ?></h3>
  <p class="muted"><?php echo hh_h(hh_admin_t('hub_modules_hint')); ?>
    <?php if (hh_sites_table_exists($pdo)): ?> · <a href="sites.php"><?php echo hh_h(hh_admin_t('sites')); ?></a><?php endif; ?>
  </p>
  <?php if (!hh_sites_table_exists($pdo)): ?>
  <p class="muted"><?php echo hh_h(hh_admin_t('sites_migration_hint')); ?></p>
  <?php else: ?>
  <form method="post" class="hub-modules-form">
    <input type="hidden" name="hub_toggle" value="1">
    <ul class="hub-module-toggles">
      <?php foreach ($hubLabels as $code => $label): ?>
      <li class="hub-module-toggle-row">
        <span class="hub-module-toggle-label"><?php echo hh_h($label); ?> <code><?php echo hh_h($code); ?></code></span>
        <label class="toggle-switch">
          <input type="checkbox" name="visible_<?php echo hh_h($code); ?>" value="1"<?php echo !empty($hubModules[$code]) ? ' checked' : ''; ?>>
          <span class="toggle-slider"></span>
        </label>
      </li>
      <?php endforeach; ?>
    </ul>
    <p><button type="submit" class="btn-primary"><?php echo hh_h(hh_admin_t('save')); ?></button></p>
  </form>
  <?php endif; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h3><?php echo hh_h(hh_admin_t('overview')); ?></h3>
    <p><?php echo hh_h(hh_admin_t('total')); ?>：<strong><?php echo (int) $total; ?></strong></p>
    <p><?php echo hh_h(hh_admin_t('published_count')); ?>：<strong><?php echo (int) $published; ?></strong></p>
    <p><?php echo hh_h(hh_admin_t('draft_count')); ?>：<strong><?php echo (int) $draft; ?></strong></p>
    <p><?php echo hh_h(hh_admin_t('pending_translate')); ?>：<strong><?php echo (int) $pendingTranslate; ?></strong></p>
    <p><?php echo hh_h(hh_admin_t('translate_ai_draft')); ?>：<strong><?php echo (int) $translateStats['ai_draft']; ?></strong></p>
    <p><?php echo hh_h(hh_admin_t('translate_human_approved')); ?>：<strong><?php echo (int) $translateStats['human_approved']; ?></strong></p>
    <p class="muted"><?php echo hh_h(hh_admin_t('translate_stats_hint')); ?></p>
    <p>
      <a class="btn" href="news_publish.php"><?php echo hh_h(hh_admin_t('news_publish')); ?></a>
      <a class="btn btn-secondary" href="publish.php"><?php echo hh_h(hh_admin_t('publish')); ?></a>
    </p>
  </div>
  <div class="card">
    <h3><?php echo hh_h(hh_admin_t('recent_jobs')); ?></h3>
    <table>
      <tr><th><?php echo hh_h(hh_admin_t('job_id')); ?></th><th><?php echo hh_h(hh_admin_t('job_type')); ?></th><th><?php echo hh_h(hh_admin_t('status')); ?></th><th><?php echo hh_h(hh_admin_t('job_result')); ?></th></tr>
      <?php foreach ($jobs as $j): ?>
      <tr>
        <td><?php echo (int) $j['id']; ?></td>
        <td><?php echo hh_h(hh_admin_status_label($j['job_type'])); ?></td>
        <td><?php echo hh_h(hh_admin_status_label($j['status'])); ?></td>
        <td><?php echo hh_h($j['message']); ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card">
  <h3><?php echo hh_h(hh_admin_t('latest_handhelds')); ?></h3>
  <p class="muted"><?php echo hh_h(hh_admin_t('latest_handhelds_hint')); ?> <a href="handheld.php"><?php echo hh_h(hh_admin_t('handheld_list_link')); ?></a></p>
  <table>
    <tr><th><?php echo hh_h(hh_admin_t('name')); ?></th><th><?php echo hh_h(hh_admin_t('brand')); ?></th><th><?php echo hh_h(hh_admin_t('release')); ?></th><th><?php echo hh_h(hh_admin_t('status')); ?></th><th><?php echo hh_h(hh_admin_t('actions')); ?></th></tr>
    <?php foreach ($recent as $h): ?>
    <tr>
      <td><?php echo hh_h($h['name_zh'] ?: $h['slug']); ?></td>
      <td><?php echo hh_h($h['brand']); ?></td>
      <td><?php echo hh_h($h['release_date']); ?></td>
      <td><span class="badge badge-<?php echo $h['status'] === 'published' ? 'published' : 'draft'; ?>"><?php echo hh_h(hh_admin_status_label($h['status'])); ?></span></td>
      <td><?php hh_admin_action_links((int) $h['id']); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php hh_admin_layout_end(); ?>
