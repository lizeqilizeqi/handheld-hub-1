<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/vertical_ui.php';
require_once dirname(__DIR__) . '/lib/vertical_layout.php';

hh_bootstrap();
$site = hh_site_code();
if (!in_array($site, hh_vertical_site_codes(), true)) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');
$title = $locale === 'zh' ? '首页' : 'Home';
$desc = hh_vertical_ui($site, $locale, 'tagline');
$canonical = hh_site_public_url($locale, $site);

hh_vertical_layout_start($site, $locale, $title, array(
    'path' => '',
    'hreflang_home' => true,
    'description' => $desc,
    'canonical' => $canonical,
    'og_type' => 'website',
    'robots' => 'noindex,follow',
));
?>
<section class="vertical-hero">
  <p class="vertical-kicker"><?php echo hh_h(hh_vertical_ui($site, $locale, 'kicker')); ?></p>
  <h1><?php echo hh_h(hh_vertical_ui($site, $locale, 'site_name')); ?></h1>
  <p class="vertical-lead"><?php echo hh_h(hh_vertical_ui($site, $locale, 'lead')); ?></p>
</section>

<section class="vertical-panels">
  <article class="vertical-panel">
    <h2><?php echo hh_h(hh_vertical_ui($site, $locale, 'panel_status_title')); ?></h2>
    <p><?php echo hh_h(hh_vertical_ui($site, $locale, 'panel_status_body')); ?></p>
  </article>
  <article class="vertical-panel">
    <h2><?php echo hh_h(hh_vertical_ui($site, $locale, 'panel_compliance_title')); ?></h2>
    <p><?php echo hh_h(hh_vertical_ui($site, $locale, 'panel_compliance_body')); ?></p>
  </article>
  <article class="vertical-panel">
    <h2><?php echo hh_h(hh_vertical_ui($site, $locale, 'panel_next_title')); ?></h2>
    <p><?php echo hh_h(hh_vertical_ui($site, $locale, 'panel_next_body')); ?></p>
  </article>
</section>

<p class="vertical-back">
  <a class="btn-primary" href="<?php echo hh_h(hh_site_public_url($locale, 'hub')); ?>"><?php echo hh_h(hh_vertical_ui($site, $locale, 'back_hub')); ?></a>
</p>
<?php hh_vertical_layout_end($site, $locale); ?>
