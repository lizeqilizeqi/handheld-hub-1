<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/site_repo.php';

hh_admin_require_login();
$pdo = hh_pdo();

if (empty($_SESSION['hh_sites_csrf'])) {
    $_SESSION['hh_sites_csrf'] = bin2hex(random_bytes(16));
}
$sitesCsrf = (string) $_SESSION['hh_sites_csrf'];

$flash = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fromDb = hh_sites_table_exists($pdo)) {
    $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if ($token === '' || !hash_equals($sitesCsrf, $token)) {
        $flashErr = hh_admin_t('sites_csrf_error');
    } else {
        $saved = 0;
        $ids = isset($_POST['section_id']) ? (array) $_POST['section_id'] : array();
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            $prefix = 'sec_' . $id . '_';
            $data = array(
                'title_en' => isset($_POST[$prefix . 'title_en']) ? (string) $_POST[$prefix . 'title_en'] : '',
                'title_zh' => isset($_POST[$prefix . 'title_zh']) ? (string) $_POST[$prefix . 'title_zh'] : '',
                'desc_en' => isset($_POST[$prefix . 'desc_en']) ? (string) $_POST[$prefix . 'desc_en'] : '',
                'desc_zh' => isset($_POST[$prefix . 'desc_zh']) ? (string) $_POST[$prefix . 'desc_zh'] : '',
                'badge' => isset($_POST[$prefix . 'badge']) ? (string) $_POST[$prefix . 'badge'] : 'soon',
                'sort_order' => isset($_POST[$prefix . 'sort_order']) ? (int) $_POST[$prefix . 'sort_order'] : 0,
                'is_visible' => !empty($_POST[$prefix . 'is_visible']),
            );
            if (hh_hub_section_save($pdo, $id, $data)) {
                $saved++;
            }
        }
        $flash = sprintf(hh_admin_t('sites_sections_saved'), $saved);
    }
}

$fromDb = hh_sites_table_exists($pdo);
$sites = hh_sites_list($pdo);
$sections = $fromDb ? hh_hub_sections_admin_list($pdo) : hh_hub_sections_list($pdo);

hh_admin_layout_start('sites');
?>
<h1><?php echo hh_h(hh_admin_t('sites')); ?></h1>
<p class="muted"><?php echo hh_h(hh_admin_t('sites_hint')); ?></p>

<?php if ($flash !== ''): ?>
<p class="flash ok"><?php echo hh_h($flash); ?></p>
<?php endif; ?>
<?php if ($flashErr !== ''): ?>
<p class="flash err"><?php echo hh_h($flashErr); ?></p>
<?php endif; ?>

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
<?php if ($fromDb): ?>
<form method="post" class="sites-sections-form" accept-charset="UTF-8">
  <input type="hidden" name="csrf" value="<?php echo hh_h($sitesCsrf); ?>">
  <?php foreach ($sections as $sec): ?>
  <?php $id = (int) $sec['id']; $p = 'sec_' . $id . '_'; ?>
  <input type="hidden" name="section_id[]" value="<?php echo $id; ?>">
  <fieldset class="sites-section-block">
    <legend><code><?php echo hh_h($sec['site_code']); ?></code></legend>
    <div class="form-grid">
      <label><?php echo hh_h(hh_admin_t('sites_title_en')); ?>
        <input type="text" name="<?php echo hh_h($p); ?>title_en" value="<?php echo hh_h($sec['title_en']); ?>">
      </label>
      <label><?php echo hh_h(hh_admin_t('sites_title_zh')); ?>
        <input type="text" name="<?php echo hh_h($p); ?>title_zh" value="<?php echo hh_h($sec['title_zh']); ?>">
      </label>
      <label class="full"><?php echo hh_h(hh_admin_t('sites_desc_en')); ?>
        <textarea name="<?php echo hh_h($p); ?>desc_en" rows="2"><?php echo hh_h((string) ($sec['desc_en'] ?? '')); ?></textarea>
      </label>
      <label class="full"><?php echo hh_h(hh_admin_t('sites_desc_zh')); ?>
        <textarea name="<?php echo hh_h($p); ?>desc_zh" rows="2"><?php echo hh_h((string) ($sec['desc_zh'] ?? '')); ?></textarea>
      </label>
      <label><?php echo hh_h(hh_admin_t('sites_badge')); ?>
        <select name="<?php echo hh_h($p); ?>badge">
          <option value="live"<?php echo $sec['badge'] === 'live' ? ' selected' : ''; ?>>live</option>
          <option value="soon"<?php echo $sec['badge'] === 'soon' ? ' selected' : ''; ?>>soon</option>
        </select>
      </label>
      <label><?php echo hh_h(hh_admin_t('sites_sort')); ?>
        <input type="number" name="<?php echo hh_h($p); ?>sort_order" value="<?php echo (int) $sec['sort_order']; ?>" style="width:5rem">
      </label>
      <label class="hub-visible-toggle">
        <span><?php echo hh_h(hh_admin_t('sites_visible')); ?></span>
        <label class="toggle-switch">
          <input type="checkbox" name="<?php echo hh_h($p); ?>is_visible" value="1"<?php echo !empty($sec['is_visible']) ? ' checked' : ''; ?>>
          <span class="toggle-slider"></span>
        </label>
      </label>
    </div>
  </fieldset>
  <?php endforeach; ?>
  <p><button type="submit" class="btn-primary"><?php echo hh_h(hh_admin_t('save')); ?></button></p>
</form>
<?php else: ?>
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
<?php endif; ?>

<h2><?php echo hh_h(hh_admin_t('sites_seo')); ?></h2>
<ul>
  <li><a href="<?php echo hh_h(hh_site_public_url('llms.txt', 'hub')); ?>" target="_blank" rel="noopener">llms.txt</a></li>
  <li><a href="<?php echo hh_h(hh_site_public_url('sitemap-index.xml', 'hub')); ?>" target="_blank" rel="noopener">sitemap-index.xml</a></li>
</ul>

<?php hh_admin_layout_end(); ?>
