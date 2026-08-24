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

$items = hh_feed_items_public($pdo, array('limit' => 8));
$total = hh_feed_items_count_public($pdo);
$hasFeed = $total > 0;

$title = $locale === 'zh' ? '首页' : 'Home';
$desc = hh_vertical_ui('news', $locale, 'tagline');
$canonical = hh_site_public_url($locale, 'news');
$robots = $hasFeed ? 'index,follow' : 'noindex,follow';

hh_vertical_layout_start('news', $locale, $title, array(
    'path' => '',
    'hreflang_home' => true,
    'description' => $desc,
    'canonical' => $canonical,
    'robots' => $robots,
));
?>
<section class="vertical-hero">
  <p class="vertical-kicker"><?php echo hh_h(hh_vertical_ui('news', $locale, 'kicker')); ?></p>
  <h1><?php echo hh_h(hh_vertical_ui('news', $locale, 'site_name')); ?></h1>
  <p class="vertical-lead"><?php echo hh_h($hasFeed
    ? ($locale === 'zh' ? '本站原创资讯，按发布日期从新到旧排列。' : 'Original articles from Old Man Hub, newest first.')
    : hh_vertical_ui('news', $locale, 'lead')); ?></p>
</section>

<?php if ($hasFeed): ?>
<section class="news-feed-preview">
  <div class="section-head">
    <h2><?php echo $locale === 'zh' ? '最新' : 'Latest'; ?></h2>
    <a href="<?php echo hh_h(hh_site_public_url($locale . '/news', 'news')); ?>"><?php echo $locale === 'zh' ? '全部资讯 →' : 'All news →'; ?></a>
  </div>
  <ul class="news-list news-list--cards">
    <?php foreach ($items as $row): ?>
    <?php hh_news_render_list_item($row, $locale); ?>
    <?php endforeach; ?>
  </ul>
</section>
<?php else: ?>
<section class="vertical-panels">
  <article class="vertical-panel">
    <h2><?php echo hh_h(hh_vertical_ui('news', $locale, 'panel_status_title')); ?></h2>
    <p><?php echo hh_h(hh_vertical_ui('news', $locale, 'panel_status_body')); ?></p>
  </article>
  <article class="vertical-panel">
    <h2><?php echo hh_h(hh_vertical_ui('news', $locale, 'panel_compliance_title')); ?></h2>
    <p><?php echo hh_h(hh_vertical_ui('news', $locale, 'panel_compliance_body')); ?></p>
  </article>
  <article class="vertical-panel">
    <h2><?php echo hh_h(hh_vertical_ui('news', $locale, 'panel_next_title')); ?></h2>
    <p><?php echo hh_h(hh_vertical_ui('news', $locale, 'panel_next_body')); ?></p>
  </article>
</section>
<?php endif; ?>

<p class="vertical-back">
  <a class="btn-primary" href="<?php echo hh_h(hh_site_public_url($locale, 'hub')); ?>"><?php echo hh_h(hh_vertical_ui('news', $locale, 'back_hub')); ?></a>
</p>
<?php hh_vertical_layout_end('news', $locale); ?>
