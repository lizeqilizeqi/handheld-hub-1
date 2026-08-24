<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/game_repo.php';
require_once dirname(__DIR__) . '/lib/vertical_ui.php';
require_once dirname(__DIR__) . '/lib/vertical_layout.php';

hh_bootstrap();
$pdo = hh_pdo();
$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');

$entries = hh_game_list($pdo, array('limit' => 6));
$total = hh_game_count($pdo);
$hasCatalog = $total > 0;

$title = $locale === 'zh' ? '首页' : 'Home';
$desc = hh_vertical_ui('game', $locale, 'tagline');
$canonical = hh_site_public_url($locale, 'game');
$robots = $hasCatalog ? 'index,follow' : 'noindex,follow';

hh_vertical_layout_start('game', $locale, $title, array(
    'path' => '',
    'hreflang_home' => true,
    'description' => $desc,
    'canonical' => $canonical,
    'og_type' => 'website',
    'robots' => $robots,
));
?>
<section class="vertical-hero">
  <p class="vertical-kicker"><?php echo hh_h(hh_vertical_ui('game', $locale, 'kicker')); ?></p>
  <h1><?php echo hh_h(hh_vertical_ui('game', $locale, 'site_name')); ?></h1>
  <p class="vertical-lead"><?php echo hh_h($hasCatalog
    ? ($locale === 'zh' ? '经典游戏资料与外链索引（不含 ROM）。' : 'Curated classic game guides and official links — no ROM hosting.')
    : hh_vertical_ui('game', $locale, 'lead')); ?></p>
</section>

<?php if ($hasCatalog): ?>
<section class="game-catalog-preview">
  <div class="section-head">
    <h2><?php echo $locale === 'zh' ? '精选条目' : 'Featured entries'; ?></h2>
    <a href="<?php echo hh_h(hh_site_public_url($locale . '/games', 'game')); ?>"><?php echo $locale === 'zh' ? '查看全部 →' : 'View all →'; ?></a>
  </div>
  <div class="grid game-grid">
    <?php foreach ($entries as $row): ?>
    <article class="card game-card">
      <a class="card-link" href="<?php echo hh_h(hh_site_public_url($locale . '/game/' . $row['slug'], 'game')); ?>">
        <div class="card-body">
          <?php if (!empty($row['platform'])): ?><p class="game-platform"><?php echo hh_h($row['platform']); ?></p><?php endif; ?>
          <h3 class="card-title"><?php echo hh_h(hh_game_title($row, $locale)); ?></h3>
          <?php $sum = hh_game_summary($row, $locale); if ($sum !== ''): ?>
          <p class="game-summary"><?php echo hh_h($sum); ?></p>
          <?php endif; ?>
        </div>
      </a>
    </article>
    <?php endforeach; ?>
  </div>
</section>
<?php else: ?>
<section class="vertical-panels">
  <article class="vertical-panel">
    <h2><?php echo hh_h(hh_vertical_ui('game', $locale, 'panel_status_title')); ?></h2>
    <p><?php echo hh_h(hh_vertical_ui('game', $locale, 'panel_status_body')); ?></p>
  </article>
  <article class="vertical-panel">
    <h2><?php echo hh_h(hh_vertical_ui('game', $locale, 'panel_compliance_title')); ?></h2>
    <p><?php echo hh_h(hh_vertical_ui('game', $locale, 'panel_compliance_body')); ?></p>
  </article>
  <article class="vertical-panel">
    <h2><?php echo hh_h(hh_vertical_ui('game', $locale, 'panel_next_title')); ?></h2>
    <p><?php echo hh_h(hh_vertical_ui('game', $locale, 'panel_next_body')); ?></p>
  </article>
</section>
<?php endif; ?>

<p class="game-compliance-note"><?php echo hh_h($locale === 'zh'
  ? '本站不提供 ROM 下载或破解资源；外链指向第三方，请自行遵守当地法律与版权规定。'
  : 'This site does not host ROMs or cracks. External links are third-party — follow your local copyright laws.'); ?></p>

<p class="vertical-back">
  <a class="btn-primary" href="<?php echo hh_h(hh_site_public_url($locale, 'hub')); ?>"><?php echo hh_h(hh_vertical_ui('game', $locale, 'back_hub')); ?></a>
</p>
<?php hh_vertical_layout_end('game', $locale); ?>
