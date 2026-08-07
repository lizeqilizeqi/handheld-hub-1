<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once dirname(__DIR__) . '/lib/brand_repo.php';
require_once dirname(__DIR__) . '/lib/public_layout.php';

hh_bootstrap();
$pdo = hh_pdo();
$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');

$total = hh_handheld_count($pdo, array('status' => 'published'));
$brandCount = count(hh_brands($pdo));
$recent = hh_handheld_list($pdo, array('status' => 'published', 'limit' => 6, 'offset' => 0));

$title = $locale === 'zh' ? '首页' : 'Home';
$desc = $locale === 'zh'
    ? '掌机百科：收录数百款掌上游戏设备，按发布时间浏览规格、参数与评测内容。'
    : 'Your independent handheld gaming encyclopedia. Explore release timelines, specs, and editorial guides for portable consoles.';

hh_public_layout_start($locale, $title, array(
    'path' => '',
    'hreflang_home' => true,
    'description' => $desc,
    'canonical' => hh_public_url($locale),
    'og_type' => 'website',
    'json_ld' => json_encode(array(
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => hh_public_ui($locale, 'site_name'),
        'url' => hh_public_url($locale),
        'description' => $desc,
        'inLanguage' => $locale === 'zh' ? 'zh-CN' : 'en',
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
));
?>
<section class="home-hero">
  <h1><?php echo hh_h(hh_public_ui($locale, 'site_name')); ?></h1>
  <p class="home-lead"><?php echo $locale === 'zh'
    ? '独立的掌上游戏设备百科。浏览各品牌掌机发布信息、屏幕规格、硬件参数与英文介绍。'
    : 'Your independent handheld gaming encyclopedia. Explore release timelines, specs, and editorial guides for portable consoles.'; ?></p>
  <div class="home-stats-cards" aria-label="<?php echo $locale === 'zh' ? '站点数据' : 'Site statistics'; ?>">
    <div class="home-stat-card">
      <span class="home-stat-value"><?php echo (int) $total; ?>+</span>
      <span class="home-stat-label"><?php echo $locale === 'zh' ? '已发布掌机' : 'Published devices'; ?></span>
    </div>
    <div class="home-stat-card">
      <span class="home-stat-value"><?php echo (int) $brandCount; ?>+</span>
      <span class="home-stat-label"><?php echo $locale === 'zh' ? '品牌' : 'Brands'; ?></span>
    </div>
    <div class="home-stat-card">
      <span class="home-stat-value">EN / 中文</span>
      <span class="home-stat-label"><?php echo $locale === 'zh' ? '双语内容' : 'Bilingual'; ?></span>
    </div>
  </div>
  <div class="home-cta">
    <a class="btn-primary" href="/<?php echo hh_h($locale); ?>/handhelds"><?php echo hh_h(hh_public_ui($locale, 'nav_handhelds')); ?></a>
    <a class="btn-secondary" href="/<?php echo hh_h($locale); ?>/about"><?php echo hh_h(hh_public_ui($locale, 'nav_about')); ?></a>
  </div>
</section>

<?php if ($recent):
  $recentBrandLogos = hh_public_brand_logos_for_list($pdo, $recent);
?>
<section class="home-recent">
  <h2><?php echo $locale === 'zh' ? '最新添加' : 'Recently added'; ?></h2>
  <div class="grid home-grid">
    <?php foreach ($recent as $h): ?>
    <?php hh_public_render_handheld_card($pdo, $locale, $h, $recentBrandLogos); ?>
    <?php endforeach; ?>
  </div>
  <p class="home-more"><a href="/<?php echo hh_h($locale); ?>/handhelds"><?php echo $locale === 'zh' ? '查看全部掌机 →' : 'View all handhelds →'; ?></a></p>
</section>
<?php endif; ?>

<?php hh_public_layout_end($locale); ?>
