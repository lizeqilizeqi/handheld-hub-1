<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/public_layout.php';
require_once dirname(__DIR__) . '/lib/public_pages.php';

hh_bootstrap();

$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');
$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';

if (!in_array($slug, hh_public_static_pages(), true)) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$page = hh_public_page_meta($locale, $slug);
if (!$page) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$useHubLayout = !empty($_GET['hub_layout']);
if ($useHubLayout) {
    require_once dirname(__DIR__) . '/lib/site_context.php';
    require_once dirname(__DIR__) . '/lib/hub_layout.php';
    hh_hub_layout_start($locale, $page['title'], array(
        'path' => $slug,
        'description' => $page['description'],
        'canonical' => hh_site_public_url($locale . '/' . $slug, 'hub'),
        'og_type' => 'website',
    ));
} else {
    hh_public_layout_start($locale, $page['title'], array(
        'path' => $slug,
        'description' => $page['description'],
        'canonical' => hh_public_url($locale . '/' . $slug),
        'og_type' => 'website',
    ));
}
?>
<article class="static-page">
  <h1><?php echo hh_h($page['title']); ?></h1>
  <?php foreach ($page['sections'] as $section): ?>
  <section class="static-section">
    <h2><?php echo hh_h($section['heading']); ?></h2>
    <div class="prose"><?php echo $section['html']; ?></div>
  </section>
  <?php endforeach; ?>
</article>
<?php
if ($useHubLayout) {
    hh_hub_layout_end($locale);
} else {
    hh_public_layout_end($locale);
}
