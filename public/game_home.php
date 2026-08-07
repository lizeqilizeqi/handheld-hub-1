<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/vertical_ui.php';
require_once dirname(__DIR__) . '/lib/vertical_layout.php';

hh_bootstrap();
$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');

$title = $locale === 'zh' ? '首页' : 'Home';
$desc = hh_vertical_ui('game', $locale, 'tagline');
$canonical = hh_site_public_url($locale, 'game');

hh_vertical_layout_start('game', $locale, $title, array(
    'path' => '',
    'hreflang_home' => true,
    'description' => $desc,
    'canonical' => $canonical,
    'og_type' => 'website',
));
?>
<section class="home-hero vertical-hero">
  <p class="vertical-badge"><?php echo hh_h(hh_vertical_ui('game', $locale, 'badge_soon')); ?></p>
  <h1><?php echo hh_h(hh_vertical_ui('game', $locale, 'site_name')); ?></h1>
  <p class="home-lead"><?php echo hh_h(hh_vertical_ui('game', $locale, 'tagline')); ?></p>
</section>
<section class="vertical-soon">
  <p><?php echo hh_h(hh_vertical_ui('game', $locale, 'lead')); ?></p>
  <p><a href="<?php echo hh_h(hh_site_public_url($locale, 'hub')); ?>"><?php echo hh_h(hh_vertical_ui('game', $locale, 'back_hub')); ?></a></p>
</section>
<?php hh_vertical_layout_end('game', $locale); ?>
