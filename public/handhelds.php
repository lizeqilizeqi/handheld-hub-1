<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';

require_once dirname(__DIR__) . '/lib/handheld_repo.php';

require_once dirname(__DIR__) . '/lib/brand_repo.php';

require_once dirname(__DIR__) . '/lib/public_layout.php';



hh_bootstrap();

$pdo = hh_pdo();

$locale = hh_public_locale(isset($_GET['locale']) ? (string) $_GET['locale'] : 'en');

$brand = isset($_GET['brand']) ? (string) $_GET['brand'] : '';

$page = max(1, (int) ($_GET['page'] ?? 1));

$perPage = 24;

$countFilters = array('status' => 'published', 'brand' => $brand);
$total = hh_handheld_count($pdo, $countFilters);
$totalPages = max(1, (int) ceil(max(0, $total) / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$filters = array('status' => 'published', 'brand' => $brand, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage);

$list = hh_handheld_list($pdo, $filters);

$brands = hh_brands($pdo);
$brandLogos = hh_public_brand_logos_for_list($pdo, $list);

$rangeStart = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$rangeEnd = min($page * $perPage, $total);

$queryBase = array_filter(array(
    'brand' => $brand !== '' ? $brand : null,
));

function hh_public_handhelds_page_url($locale, $queryBase, $pageNum)
{
    $params = $queryBase;
    if ($pageNum > 1) {
        $params['page'] = $pageNum;
    }
    $qs = http_build_query($params);
    return '/' . $locale . '/handhelds' . ($qs !== '' ? '?' . $qs : '');
}



$title = hh_public_page_title($locale, 'handhelds');
$canonicalPath = $locale . '/handhelds';
if ($page > 1 || $brand !== '') {
    $canonicalParams = array();
    if ($brand !== '') {
        $canonicalParams['brand'] = $brand;
    }
    if ($page > 1) {
        $canonicalParams['page'] = $page;
    }
    $canonicalPath .= '?' . http_build_query($canonicalParams);
}

hh_public_layout_start($locale, $title, array(
    'path' => 'handhelds',
    'description' => $locale === 'zh' ? '按发布时间排序的掌机百科，收录各品牌掌上游戏设备规格与参数。' : 'Handheld gaming devices sorted by release date — specs, brands, and portable console guides.',
    'canonical' => hh_public_url($canonicalPath),
    'og_type' => 'website',
));

?>

<section class="hero">

  <h1><?php echo hh_h($title); ?></h1>

  <p class="muted"><?php echo hh_h(hh_public_ui($locale, 'sort_hint')); ?></p>
  <?php if ($total > 0): ?>
  <p class="muted list-summary"><?php echo hh_h(sprintf(hh_public_ui($locale, 'pagination_range'), $rangeStart, $rangeEnd, $total)); ?></p>
  <?php endif; ?>

  <form class="filters" method="get" action="/<?php echo hh_h($locale); ?>/handhelds">

    <select name="brand" onchange="this.form.submit()">

      <option value=""><?php echo hh_h(hh_public_ui($locale, 'all_brands')); ?></option>

      <?php foreach ($brands as $b): ?>

      <option value="<?php echo hh_h($b['brand']); ?>"<?php echo $brand === $b['brand'] ? ' selected' : ''; ?>><?php echo hh_h($b['brand']); ?></option>

      <?php endforeach; ?>

    </select>

  </form>

</section>

<div class="grid">

  <?php foreach ($list as $h): ?>
  <?php hh_public_render_handheld_card($pdo, $locale, $h, $brandLogos); ?>
  <?php endforeach; ?>

</div>

<?php if (count($list) === 0): ?>

<p class="muted"><?php echo hh_h(hh_public_ui($locale, 'no_handhelds')); ?></p>

<?php endif; ?>

<?php if ($totalPages > 1): ?>
<nav class="pagination" aria-label="<?php echo $locale === 'zh' ? '分页' : 'Pagination'; ?>">
  <?php if ($page > 1): ?>
  <a class="pagination-btn" href="<?php echo hh_h(hh_public_handhelds_page_url($locale, $queryBase, $page - 1)); ?>"><?php echo hh_h(hh_public_ui($locale, 'pagination_prev')); ?></a>
  <?php else: ?>
  <span class="pagination-btn is-disabled"><?php echo hh_h(hh_public_ui($locale, 'pagination_prev')); ?></span>
  <?php endif; ?>

  <span class="pagination-info">
    <?php echo hh_h(sprintf(hh_public_ui($locale, 'pagination_page'), $page, $totalPages)); ?>
    · <?php echo hh_h(sprintf(hh_public_ui($locale, 'pagination_total'), $total)); ?>
  </span>

  <?php if ($page < $totalPages): ?>
  <a class="pagination-btn" href="<?php echo hh_h(hh_public_handhelds_page_url($locale, $queryBase, $page + 1)); ?>"><?php echo hh_h(hh_public_ui($locale, 'pagination_next')); ?></a>
  <?php else: ?>
  <span class="pagination-btn is-disabled"><?php echo hh_h(hh_public_ui($locale, 'pagination_next')); ?></span>
  <?php endif; ?>
</nav>
<?php endif; ?>

<?php hh_public_layout_end($locale); ?>

