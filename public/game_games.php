<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/game_repo.php';
require_once dirname(__DIR__) . '/lib/vertical_ui.php';
require_once dirname(__DIR__) . '/lib/vertical_layout.php';

hh_bootstrap();
$pdo = hh_pdo();
$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');
$platform = isset($_GET['platform']) ? trim((string) $_GET['platform']) : '';

$platforms = hh_game_platforms($pdo);
$entries = hh_game_list($pdo, array('platform' => $platform, 'limit' => 200));
$total = hh_game_count($pdo, 'published', $platform);

$title = $locale === 'zh' ? '游戏目录' : 'Game catalog';
$desc = $locale === 'zh' ? '经典游戏资料目录（元数据与合规外链）。' : 'Classic game catalog — metadata and official links only.';

hh_vertical_layout_start('game', $locale, $title, array(
    'path' => 'games',
    'description' => $desc,
    'canonical' => hh_site_public_url($locale . '/games', 'game'),
    'robots' => $total > 0 ? 'index,follow' : 'noindex,follow',
));
?>
<section class="page-head">
  <h1><?php echo hh_h($title); ?></h1>
  <p class="muted"><?php echo hh_h($desc); ?></p>
</section>

<?php if ($platforms): ?>
<nav class="game-filters" aria-label="<?php echo $locale === 'zh' ? '平台筛选' : 'Platform filter'; ?>">
  <a class="<?php echo $platform === '' ? 'is-active' : ''; ?>" href="<?php echo hh_h(hh_site_public_url($locale . '/games', 'game')); ?>"><?php echo $locale === 'zh' ? '全部' : 'All'; ?></a>
  <?php foreach ($platforms as $pl): ?>
  <a class="<?php echo $platform === $pl ? 'is-active' : ''; ?>" href="<?php echo hh_h(hh_site_public_url($locale . '/games', 'game') . '?platform=' . rawurlencode($pl)); ?>"><?php echo hh_h($pl); ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if (!$entries): ?>
<p><?php echo hh_h($locale === 'zh' ? '暂无已发布条目。' : 'No published entries yet.'); ?></p>
<?php else: ?>
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
<?php endif; ?>
<?php hh_vertical_layout_end('game', $locale); ?>
