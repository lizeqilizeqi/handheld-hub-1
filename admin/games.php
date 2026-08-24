<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/game_repo.php';
require_once dirname(__DIR__) . '/lib/site_context.php';

hh_admin_require_login();
$pdo = hh_pdo();
$msg = '';
$err = '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $row = hh_game_by_id($pdo, $id);
    if (!$row) {
        $err = hh_admin_t('not_found');
    } else {
        try {
            hh_game_save($pdo, array(
                'slug' => $_POST['slug'] ?? $row['slug'],
                'platform' => $_POST['platform'] ?? '',
                'title_en' => $_POST['title_en'] ?? '',
                'title_zh' => $_POST['title_zh'] ?? '',
                'summary_en' => $_POST['summary_en'] ?? '',
                'summary_zh' => $_POST['summary_zh'] ?? '',
                'body_en' => $_POST['body_en'] ?? '',
                'body_zh' => $_POST['body_zh'] ?? '',
                'external_url' => $_POST['external_url'] ?? '',
                'status' => $_POST['status'] ?? 'draft',
                'sort_order' => $_POST['sort_order'] ?? 0,
            ), $id);
            $msg = hh_admin_t('saved');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/layout.php';
hh_admin_layout_start('games');
?>
<h1><?php echo hh_h(hh_admin_t('games')); ?></h1>
<p class="muted"><?php echo hh_h(hh_admin_t('games_list_hint')); ?></p>

<?php if ($msg): ?><p class="flash ok"><?php echo hh_h($msg); ?></p><?php endif; ?>
<?php if ($err): ?><p class="flash err"><?php echo hh_h($err); ?></p><?php endif; ?>

<?php if ($id > 0 && ($row = hh_game_by_id($pdo, $id))): ?>
<div class="card">
  <h3><?php echo hh_h($row['title_zh'] ?: $row['title_en'] ?: $row['slug']); ?></h3>
  <p class="muted"><a href="games.php"><?php echo hh_h(hh_admin_t('back_list')); ?></a> · <?php hh_admin_game_action_links((int) $row['id'], (string) $row['slug']); ?></p>
  <form method="post">
    <div class="form-grid games-form-grid">
      <label>slug<input name="slug" value="<?php echo hh_h($row['slug']); ?>"></label>
      <label><?php echo hh_h(hh_admin_t('games_platform')); ?><input name="platform" value="<?php echo hh_h($row['platform']); ?>"></label>
      <label><?php echo hh_h(hh_admin_t('games_title_zh')); ?><input name="title_zh" value="<?php echo hh_h($row['title_zh']); ?>"></label>
      <label><?php echo hh_h(hh_admin_t('games_title_en')); ?><input name="title_en" value="<?php echo hh_h($row['title_en']); ?>"></label>
      <label class="full"><?php echo hh_h(hh_admin_t('games_summary_zh')); ?><textarea name="summary_zh" rows="2"><?php echo hh_h($row['summary_zh']); ?></textarea></label>
      <label class="full"><?php echo hh_h(hh_admin_t('games_summary_en')); ?><textarea name="summary_en" rows="2"><?php echo hh_h($row['summary_en']); ?></textarea></label>
      <label><?php echo hh_h(hh_admin_t('status')); ?>
        <select name="status">
          <option value="draft"<?php echo $row['status'] === 'draft' ? ' selected' : ''; ?>>draft</option>
          <option value="published"<?php echo $row['status'] === 'published' ? ' selected' : ''; ?>>published</option>
        </select>
      </label>
      <label><?php echo hh_h(hh_admin_t('sites_sort')); ?><input type="number" name="sort_order" value="<?php echo (int) $row['sort_order']; ?>"></label>
    </div>
    <p><button type="submit" class="btn-primary"><?php echo hh_h(hh_admin_t('save')); ?></button>
    <a class="btn-secondary" href="translate.php?game_id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('generate_en')); ?></a></p>
  </form>
</div>
<?php else: ?>
<div class="card">
  <form method="get">
    <label><?php echo hh_h(hh_admin_t('search')); ?></label>
    <input name="q" value="<?php echo hh_h($q); ?>" placeholder="标题、slug、平台">
    <button type="submit"><?php echo hh_h(hh_admin_t('search')); ?></button>
  </form>
</div>
<div class="card">
  <?php if (!hh_game_table_exists($pdo)): ?>
  <p class="muted"><?php echo hh_h(hh_admin_t('games_migration_hint')); ?></p>
  <?php else: ?>
  <?php
    $list = hh_game_admin_list($pdo, 200);
    if ($q !== '') {
        $list = array_values(array_filter($list, function ($r) use ($q) {
            $hay = strtolower($r['title_zh'] . ' ' . $r['title_en'] . ' ' . $r['slug'] . ' ' . $r['platform']);
            return strpos($hay, strtolower($q)) !== false;
        }));
    }
  ?>
  <table class="table">
    <tr>
      <th><?php echo hh_h(hh_admin_t('name')); ?></th>
      <th><?php echo hh_h(hh_admin_t('games_platform')); ?></th>
      <th><?php echo hh_h(hh_admin_t('status')); ?></th>
      <th><?php echo hh_h(hh_admin_t('actions')); ?></th>
    </tr>
    <?php foreach ($list as $g): ?>
    <tr>
      <td><?php echo hh_h(($g['title_zh'] ?: $g['title_en']) ?: $g['slug']); ?></td>
      <td><?php echo hh_h($g['platform']); ?></td>
      <td><?php echo hh_h($g['status']); ?></td>
      <td><?php hh_admin_game_action_links((int) $g['id'], (string) $g['slug']); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if ($list === array()): ?><p class="muted"><?php echo hh_h(hh_admin_t('games_empty')); ?></p><?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php hh_admin_layout_end(); ?>
