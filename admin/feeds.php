<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/feed_repo.php';
require_once dirname(__DIR__) . '/lib/site_context.php';

hh_admin_require_login();
$pdo = hh_pdo();
$msg = '';
$err = '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $act = isset($_POST['act']) ? (string) $_POST['act'] : '';
    if ($id > 0 && $act === 'delete') {
        $st = $pdo->prepare('DELETE FROM hh_feed_items WHERE id = ? LIMIT 1');
        $st->execute(array($id));
        $msg = hh_admin_t('news_deleted');
    }
}

require_once __DIR__ . '/layout.php';
hh_admin_layout_start('feeds');
?>
<h1><?php echo hh_h(hh_admin_t('feeds')); ?></h1>
<p class="muted"><?php echo hh_h(hh_admin_t('feeds_list_hint')); ?>
  · <a class="btn-primary" href="news_publish.php"><?php echo hh_h(hh_admin_t('news_publish_new')); ?></a>
</p>

<?php if ($msg): ?><p class="flash ok"><?php echo hh_h($msg); ?></p><?php endif; ?>
<?php if ($err): ?><p class="flash err"><?php echo hh_h($err); ?></p><?php endif; ?>

<div class="card">
  <form method="get">
    <label><?php echo hh_h(hh_admin_t('search')); ?></label>
    <input name="q" value="<?php echo hh_h($q); ?>" placeholder="标题、slug">
    <button type="submit"><?php echo hh_h(hh_admin_t('search')); ?></button>
  </form>
</div>
<div class="card">
  <?php if (!hh_feed_tables_exist($pdo)): ?>
  <p class="muted"><?php echo hh_h(hh_admin_t('feeds_migration_hint')); ?></p>
  <?php else: ?>
  <?php $list = hh_feed_items_admin_list($pdo, array('q' => $q, 'limit' => 150)); ?>
  <table class="table">
    <tr>
      <th><?php echo hh_h(hh_admin_t('name')); ?></th>
      <th><?php echo hh_h(hh_admin_t('release')); ?></th>
      <th><?php echo hh_h(hh_admin_t('status')); ?></th>
      <th><?php echo hh_h(hh_admin_t('actions')); ?></th>
    </tr>
    <?php foreach ($list as $it): ?>
    <tr>
      <td><?php echo hh_h($it['title_zh'] ?: $it['title']); ?></td>
      <td><?php echo !empty($it['published_at']) ? hh_h(substr((string) $it['published_at'], 0, 16)) : '—'; ?></td>
      <td><?php echo hh_h($it['status']); ?></td>
      <td>
        <a href="news_publish.php?id=<?php echo (int) $it['id']; ?>"><?php echo hh_h(hh_admin_t('edit')); ?></a>
        <?php hh_admin_news_action_links((int) $it['id'], (string) $it['slug']); ?>
        <form method="post" class="inline-form" onsubmit="return confirm('确定删除？');">
          <input type="hidden" name="id" value="<?php echo (int) $it['id']; ?>">
          <input type="hidden" name="act" value="delete">
          <button type="submit" class="btn-danger btn-inline"><?php echo hh_h(hh_admin_t('delete')); ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if ($list === array()): ?><p class="muted"><?php echo hh_h(hh_admin_t('feeds_empty')); ?></p><?php endif; ?>
  <?php endif; ?>
</div>
<?php hh_admin_layout_end(); ?>
