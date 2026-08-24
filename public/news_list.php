<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/feed_repo.php';
require_once dirname(__DIR__) . '/lib/vertical_ui.php';
require_once dirname(__DIR__) . '/lib/vertical_layout.php';
require_once dirname(__DIR__) . '/lib/news_ui.php';

hh_bootstrap();
$pdo = hh_pdo();
$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 20;
$total = hh_feed_items_count_public($pdo);
$offset = ($page - 1) * $perPage;
$items = hh_feed_items_public($pdo, array('limit' => $perPage, 'offset' => $offset));
$pages = max(1, (int) ceil($total / $perPage));

$title = $locale === 'zh' ? '资讯' : 'News';
$desc = $locale === 'zh' ? 'Retro 硬件与文化资讯摘要。' : 'Retro hardware and culture headlines — summaries only.';

hh_vertical_layout_start('news', $locale, $title, array(
    'path' => 'news',
    'description' => $desc,
    'canonical' => hh_site_public_url($locale . '/news', 'news'),
    'robots' => $total > 0 ? 'index,follow' : 'noindex,follow',
));
?>
<section class="page-head">
  <h1><?php echo hh_h($title); ?></h1>
  <p class="muted"><?php echo hh_h($desc); ?></p>
</section>

<?php if (!$items): ?>
<p><?php echo hh_h($locale === 'zh' ? '暂无已发布资讯。' : 'No published items yet.'); ?></p>
<?php else: ?>
<ul class="news-list news-list--cards">
  <?php foreach ($items as $row): ?>
  <?php hh_news_render_list_item($row, $locale); ?>
  <?php endforeach; ?>
</ul>
<?php if ($pages > 1): ?>
<nav class="news-pagination" aria-label="Pagination">
  <?php if ($page > 1): ?>
  <a href="<?php echo hh_h(hh_site_public_url($locale . '/news', 'news') . '?page=' . ($page - 1)); ?>"><?php echo $locale === 'zh' ? '上一页' : 'Previous'; ?></a>
  <?php endif; ?>
  <span><?php echo $locale === 'zh' ? '第' : 'Page'; ?> <?php echo (int) $page; ?> / <?php echo (int) $pages; ?></span>
  <?php if ($page < $pages): ?>
  <a href="<?php echo hh_h(hh_site_public_url($locale . '/news', 'news') . '?page=' . ($page + 1)); ?>"><?php echo $locale === 'zh' ? '下一页' : 'Next'; ?></a>
  <?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php hh_vertical_layout_end('news', $locale); ?>
