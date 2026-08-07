<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/site_repo.php';

hh_admin_require_login();
$pdo = hh_pdo();

$fromDb = hh_sites_table_exists($pdo);
$sites = hh_sites_list($pdo);
$sections = hh_hub_sections_list($pdo);

hh_admin_layout_start('sites');
?>
<h1><?php echo hh_h(hh_admin_t('sites')); ?></h1>
<p class="muted"><?php echo hh_h(hh_admin_t('sites_hint')); ?></p>

<?php if (!$fromDb): ?>
<p class="muted" style="padding:.75rem;border:1px solid #f59e0b;border-radius:8px;background:#fffbeb;"><?php echo hh_h(hh_admin_t('sites_migration_hint')); ?></p>
<?php endif; ?>

<table class="table">
  <thead>
    <tr>
      <th><?php echo hh_h(hh_admin_t('sites_code')); ?></th>
      <th><?php echo hh_h(hh_admin_t('sites_name')); ?></th>
      <th>base_url</th>
      <th><?php echo hh_h(hh_admin_t('sites_hosts')); ?></th>
      <th><?php echo hh_h(hh_admin_t('status')); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($sites as $s): ?>
    <tr>
      <td><code><?php echo hh_h($s['code']); ?></code></td>
      <td><?php echo hh_h($s['name_zh'] . ' / ' . $s['name_en']); ?></td>
      <td><a href="<?php echo hh_h($s['base_url']); ?>" target="_blank" rel="noopener"><?php echo hh_h($s['base_url']); ?></a></td>
      <td><?php echo hh_h(implode(', ', $s['hosts'])); ?></td>
      <td><?php echo hh_h($s['status']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($sections !== array()): ?>
<h2><?php echo hh_h(hh_admin_t('sites_hub_sections')); ?></h2>
<table class="table">
  <thead>
    <tr>
      <th>site</th>
      <th><?php echo hh_h(hh_admin_t('sites_name')); ?></th>
      <th>badge</th>
      <th>entry</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($sections as $sec): ?>
    <tr>
      <td><code><?php echo hh_h($sec['site_code']); ?></code></td>
      <td><?php echo hh_h($sec['title_zh'] . ' / ' . $sec['title_en']); ?></td>
      <td><?php echo hh_h($sec['badge']); ?></td>
      <td><?php echo hh_h($sec['entry_path']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2><?php echo hh_h(hh_admin_t('sites_seo')); ?></h2>
<ul>
  <li><a href="<?php echo hh_h(hh_site_public_url('llms.txt', 'hub')); ?>" target="_blank" rel="noopener">llms.txt</a></li>
  <li><a href="<?php echo hh_h(hh_site_public_url('sitemap-index.xml', 'hub')); ?>" target="_blank" rel="noopener">sitemap-index.xml</a></li>
</ul>

<?php hh_admin_layout_end(); ?>
