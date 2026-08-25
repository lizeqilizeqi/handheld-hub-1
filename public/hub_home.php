<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once dirname(__DIR__) . '/lib/brand_repo.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/site_repo.php';
require_once dirname(__DIR__) . '/lib/hub_layout.php';

hh_bootstrap();
$pdo = hh_pdo();
$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');

$total = hh_handheld_count($pdo, array('status' => 'published'));
$recent = hh_handheld_list($pdo, array('status' => 'published', 'limit' => 4, 'offset' => 0));
$recentBrandLogos = $recent ? hh_public_brand_logos_for_list($pdo, $recent) : array();

$title = $locale === 'zh' ? '首页' : 'Home';
$desc = hh_hub_ui($locale, 'tagline');
$handheldsBase = hh_site_public_url('', 'handhelds');
$hubSections = hh_hub_sections_for_home($pdo, $locale);
$hubModules = hh_hub_modules_visible_map($pdo);
$sectionCount = count($hubSections);

hh_hub_layout_start($locale, $title, array(
    'path' => '',
    'hreflang_home' => true,
    'description' => $desc,
    'canonical' => hh_site_public_url($locale, 'hub'),
    'og_type' => 'website',
    'json_ld' => json_encode(array(
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => hh_hub_ui($locale, 'site_name'),
        'url' => hh_site_public_url($locale, 'hub'),
        'description' => $desc,
        'inLanguage' => $locale === 'zh' ? 'zh-CN' : 'en',
        'potentialAction' => array(
            '@type' => 'SearchAction',
            'target' => hh_site_public_url($locale . '/handhelds', 'handhelds') . '?brand={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ),
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
));
?>
<section class="home-hero hub-hero">
  <h1><?php echo hh_h(hh_hub_ui($locale, 'site_name')); ?></h1>
  <p class="home-lead"><?php echo hh_h(hh_hub_ui($locale, 'tagline')); ?></p>
</section>

<section class="hub-sections hub-sections--count-<?php echo max(1, min(3, $sectionCount)); ?>" aria-label="<?php echo $locale === 'zh' ? '站点板块' : 'Hub sections'; ?>">
  <?php foreach ($hubSections as $sec): ?>
  <?php
    $isLive = ($sec['badge'] === 'live');
    $cardClass = 'hub-section-card' . ($isLive ? ' hub-section-card--live' : ' hub-section-card--soon');
    $badgeLabel = $isLive ? hh_hub_ui($locale, 'badge_live') : hh_hub_ui($locale, 'badge_soon');
    $badgeClass = $isLive ? 'hub-badge hub-badge--live' : 'hub-badge';
    $url = isset($sec['url']) ? (string) $sec['url'] : '';
  ?>
  <article class="<?php echo hh_h($cardClass); ?>">
    <div class="hub-section-head">
      <h2><?php echo hh_h($sec['title']); ?></h2>
      <span class="<?php echo hh_h($badgeClass); ?>"><?php echo hh_h($badgeLabel); ?></span>
    </div>
    <div class="hub-section-body">
      <p><?php echo hh_h($sec['desc']); ?></p>
      <?php if ($isLive && $sec['site_code'] === 'handhelds'): ?>
      <p class="hub-section-stat"><strong><?php echo (int) $total; ?>+</strong> <?php echo $locale === 'zh' ? '已发布机型' : 'published devices'; ?></p>
      <?php endif; ?>
    </div>
    <p class="hub-section-cta">
      <?php if ($url !== ''): ?>
      <a class="btn-primary" href="<?php echo hh_h($url); ?>"><?php echo hh_h(hh_hub_ui($locale, 'cta_enter')); ?></a>
      <?php else: ?>
      <span class="btn-secondary hub-btn-disabled"><?php echo hh_h($badgeLabel); ?></span>
      <?php endif; ?>
    </p>
  </article>
  <?php endforeach; ?>
</section>

<?php if ($recent && !empty($hubModules['handhelds'])): ?>
<section class="home-recent hub-handhelds-preview">
  <h2><?php echo hh_h(hh_hub_ui($locale, 'handhelds_latest')); ?></h2>
  <div class="grid home-grid">
    <?php foreach ($recent as $h): ?>
    <?php
      $content = hh_handheld_content($pdo, (int) $h['id'], $locale);
      $name = ($content && !empty($content['title'])) ? (string) $content['title'] : hh_public_list_name($h);
      $imgRow = hh_handheld_cover_image($pdo, (int) $h['id']);
      $img = ($imgRow && !empty($imgRow['path'])) ? hh_image_public_url($imgRow['path']) : '';
      $brandLogo = isset($recentBrandLogos[$h['brand']]) ? $recentBrandLogos[$h['brand']] : '';
      $detailUrl = hh_site_public_url($locale . '/handheld/' . $h['slug'], 'handhelds');
    ?>
    <article class="card">
      <a class="card-link" href="<?php echo hh_h($detailUrl); ?>">
        <?php if ($img): ?><figure class="card-media"><img src="<?php echo hh_h($img); ?>" alt="<?php echo hh_h($name); ?>" loading="lazy"></figure><?php endif; ?>
        <div class="card-body">
          <div class="card-title-row">
            <?php if ($brandLogo): ?>
            <img class="card-brand-logo" src="<?php echo hh_h($brandLogo); ?>" alt="<?php echo hh_h($h['brand']); ?>" loading="lazy">
            <?php endif; ?>
            <h3 class="card-title"><?php echo hh_h($name); ?></h3>
          </div>
        </div>
      </a>
    </article>
    <?php endforeach; ?>
  </div>
  <p class="home-more"><a href="<?php echo hh_h(hh_site_public_url($locale . '/handhelds', 'handhelds')); ?>"><?php echo hh_h(hh_hub_ui($locale, 'handhelds_more')); ?></a></p>
</section>
<?php endif; ?>

<?php hh_hub_layout_end($locale); ?>
