<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login();

require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once dirname(__DIR__) . '/lib/translate_service.php';
require_once __DIR__ . '/layout.php';

$pdo = hh_pdo();
$total = hh_handheld_count($pdo, array());
$published = hh_handheld_count($pdo, array('status' => 'published'));
$draft = hh_handheld_count($pdo, array('status' => 'draft'));
$pendingTranslate = count(hh_translate_pending_handheld_ids($pdo, false));
$translateStats = hh_translate_review_counts($pdo);
$recent = hh_handheld_list($pdo, array('limit' => 10));

$jobs = hh_scrape_jobs_recent($pdo, 5);



hh_admin_layout_start('dashboard');

?>

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

    <p><a class="btn" href="scrape.php"><?php echo hh_h(hh_admin_t('run_scrape')); ?></a>
    <a class="btn btn-secondary" href="publish.php"><?php echo hh_h(hh_admin_t('publish')); ?></a></p>

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

<?php

hh_admin_layout_end();

