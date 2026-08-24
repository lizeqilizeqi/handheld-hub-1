<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/feed_repo.php';
require_once dirname(__DIR__) . '/lib/news_editor.php';
require_once dirname(__DIR__) . '/lib/vertical_ui.php';
require_once dirname(__DIR__) . '/lib/vertical_layout.php';
require_once dirname(__DIR__) . '/lib/scraper/mydrivers_news.php';

hh_bootstrap();
$pdo = hh_pdo();
$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');
$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';

$row = hh_feed_item_by_slug($pdo, $slug);
if (!$row) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$title = hh_feed_item_title($row, $locale);
$summary = hh_feed_strip_html(hh_feed_item_summary($row, $locale));
$bodyRaw = hh_feed_item_body($row, $locale);
$bodyHtml = hh_feed_item_is_manual($row)
    ? hh_news_editor_sanitize_html($bodyRaw)
    : hh_mydrivers_sanitize_body_html($bodyRaw);
$cover = hh_feed_item_image_url($row);
$original = (string) $row['link'];
$pageCanonical = hh_site_public_url($locale . '/news/' . $row['slug'], 'news');
$sourceLabel = hh_feed_source_label($row, $locale);
$dateStr = !empty($row['published_at']) ? substr((string) $row['published_at'], 0, 16) : '';
$ogImage = $cover !== '' ? $cover : hh_site_public_url('assets/og-default.svg', 'news');

hh_vertical_layout_start('news', $locale, $title, array(
    'path' => 'news/' . $row['slug'],
    'description' => hh_feed_summary_excerpt($summary !== '' ? $summary : hh_feed_strip_html($bodyHtml), 160),
    'canonical' => $pageCanonical,
    'og_type' => 'article',
    'og_image' => $ogImage,
    'robots' => 'index,follow',
));
?>
<article class="news-detail">
  <p class="news-detail-back"><a href="<?php echo hh_h(hh_site_public_url($locale . '/news', 'news')); ?>"><?php echo $locale === 'zh' ? '← 资讯列表' : '← News list'; ?></a></p>

  <?php if ($dateStr !== '' || $sourceLabel !== ''): ?>
  <p class="news-detail-meta">
    <?php if ($dateStr !== ''): ?>
    <time datetime="<?php echo hh_h(substr($dateStr, 0, 10)); ?>"><?php echo hh_h($dateStr); ?></time>
    <?php endif; ?>
    <?php if ($sourceLabel !== ''): ?>
    <?php if ($dateStr !== ''): ?> · <?php endif; ?>
    <span><?php echo hh_h($sourceLabel); ?></span>
    <?php endif; ?>
  </p>
  <?php endif; ?>

  <h1><?php echo hh_h($title); ?></h1>

  <?php if ($cover !== ''): ?>
  <figure class="news-detail-cover"><img src="<?php echo hh_h($cover); ?>" alt="" loading="eager"></figure>
  <?php endif; ?>

  <?php if ($summary !== '' && $bodyHtml === ''): ?>
  <p class="news-detail-summary"><?php echo hh_h($summary); ?></p>
  <?php endif; ?>

  <?php if ($bodyHtml !== ''): ?>
  <div class="news-detail-body prose"><?php echo $bodyHtml; ?></div>
  <?php endif; ?>

  <?php if ($original !== '' && !hh_feed_item_is_manual($row)): ?>
  <p class="news-read-original">
    <a class="btn-primary" href="<?php echo hh_h($original); ?>" rel="noopener noreferrer"><?php echo $locale === 'zh' ? '阅读原文 →' : 'Read original article →'; ?></a>
  </p>
  <?php endif; ?>
</article>
<?php hh_vertical_layout_end('news', $locale); ?>
