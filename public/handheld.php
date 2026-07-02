<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';

require_once dirname(__DIR__) . '/lib/handheld_repo.php';

require_once dirname(__DIR__) . '/lib/public_layout.php';



hh_bootstrap();

$pdo = hh_pdo();

$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');

$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';



$h = hh_handheld_by_slug($pdo, $slug);

if (!$h || $h['status'] !== 'published') {

    http_response_code(404);

    echo '404 Not Found';

    exit;

}



$content = hh_public_content_for($pdo, (int) $h['id'], $locale);

$specs = hh_handheld_specs($pdo, (int) $h['id'], $locale);

$name = hh_public_display_name($h, $locale);

$detailImg = hh_handheld_detail_image($pdo, (int) $h['id']);

$cover = $detailImg && !empty($detailImg['path']) ? hh_image_public_url($detailImg['path']) : '';



$bpZh = hh_blogger_post_row($pdo, (int) $h['id'], 'zh');

$bpEn = hh_blogger_post_row($pdo, (int) $h['id'], 'en');



hh_public_layout_start($locale, $content['title'] ?? $name, array(

    'path' => 'handheld/' . $h['slug'],

    'description' => $content['meta_description'] ?? ($content['summary'] ?? $name),

    'canonical' => hh_public_url($locale . '/handheld/' . $h['slug']),

    'og_image' => $cover,

));

?>

<article>

  <h1><?php echo hh_h($content['title'] ?? $name); ?></h1>

  <p class="muted"><?php echo hh_h($h['brand']); ?> · <?php echo hh_h($h['release_date']); ?></p>

  <?php if ($cover): ?><img class="detail-cover" src="<?php echo hh_h($cover); ?>" alt="<?php echo hh_h($name); ?>"><?php endif; ?>

  <?php if (!empty($content['summary'])): ?><p><?php echo hh_h($content['summary']); ?></p><?php endif; ?>

  <div class="prose"><?php echo $content['body_html'] ?? ''; ?></div>

  <?php echo hh_specs_table_html($specs, $locale); ?>

  <?php if ($bpZh && !empty($bpZh['blogger_url']) || $bpEn && !empty($bpEn['blogger_url'])): ?>

  <div class="blogger-links">

    <?php if ($bpZh && !empty($bpZh['blogger_url'])): ?>

    <p><a href="<?php echo hh_h($bpZh['blogger_url']); ?>" target="_blank" rel="noopener"><?php echo hh_h(hh_public_ui($locale, 'blogger_read_zh')); ?></a></p>

    <?php endif; ?>

    <?php if ($bpEn && !empty($bpEn['blogger_url'])): ?>

    <p><a href="<?php echo hh_h($bpEn['blogger_url']); ?>" target="_blank" rel="noopener"><?php echo hh_h(hh_public_ui($locale, 'blogger_read_en')); ?></a></p>

    <?php endif; ?>

  </div>

  <?php endif; ?>

</article>

<?php hh_public_layout_end($locale); ?>

