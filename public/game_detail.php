<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/game_repo.php';
require_once dirname(__DIR__) . '/lib/vertical_ui.php';
require_once dirname(__DIR__) . '/lib/vertical_layout.php';

hh_bootstrap();
$pdo = hh_pdo();
$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');
$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';

$row = hh_game_by_slug($pdo, $slug);
if (!$row) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$title = hh_game_title($row, $locale);
$desc = hh_game_summary($row, $locale);
$body = hh_game_body($row, $locale);
$canonical = hh_site_public_url($locale . '/game/' . $row['slug'], 'game');

hh_vertical_layout_start('game', $locale, $title, array(
    'path' => 'game/' . $row['slug'],
    'description' => $desc,
    'canonical' => $canonical,
    'og_type' => 'article',
    'robots' => 'index,follow',
));
?>
<article class="game-detail">
  <p class="game-detail-back"><a href="<?php echo hh_h(hh_site_public_url($locale . '/games', 'game')); ?>"><?php echo $locale === 'zh' ? '← 目录' : '← Catalog'; ?></a></p>
  <?php if (!empty($row['platform'])): ?><p class="game-platform"><?php echo hh_h($row['platform']); ?></p><?php endif; ?>
  <h1><?php echo hh_h($title); ?></h1>
  <?php if ($desc !== ''): ?><p class="game-detail-lead"><?php echo hh_h($desc); ?></p><?php endif; ?>
  <?php if ($body !== ''): ?>
  <div class="game-detail-body"><?php echo nl2br(hh_h($body)); ?></div>
  <?php endif; ?>
  <?php if (!empty($row['external_url'])): ?>
  <p class="game-external"><a href="<?php echo hh_h($row['external_url']); ?>" rel="noopener noreferrer"><?php echo $locale === 'zh' ? '官方 / 参考链接' : 'Official / reference link'; ?> →</a></p>
  <?php endif; ?>
  <p class="game-compliance-note"><?php echo hh_h($locale === 'zh'
    ? '不提供 ROM 或模拟器文件下载。'
    : 'No ROM or emulator files are provided on this site.'); ?></p>
</article>
<?php hh_vertical_layout_end('game', $locale); ?>
