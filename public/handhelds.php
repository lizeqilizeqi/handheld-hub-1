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
$brandLogos = hh_brand_logo_map($pdo, array_map(function ($row) {
    return (string) $row['brand'];
}, $list));

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

hh_public_layout_start($locale, $title, array(

    'path' => 'handhelds',

    'description' => $locale === 'zh' ? '按发布时间排序的掌机百科' : 'Handheld gaming devices sorted by release date',

    'canonical' => hh_public_url($locale . '/handhelds'),

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

  <?php foreach ($list as $h):

    $name = hh_public_list_name($h);

    $imgRow = hh_handheld_cover_image($pdo, (int) $h['id']);

    $img = ($imgRow && !empty($imgRow['path'])) ? hh_image_public_url($imgRow['path']) : '';

    $brandLogo = isset($brandLogos[$h['brand']]) ? $brandLogos[$h['brand']] : '';

    $screenSize = trim((string) $h['screen_size']);

    $screenRatio = trim((string) $h['screen_ratio']);

  ?>

  <article class="card">

  <a class="card-link" href="/<?php echo hh_h($locale); ?>/handheld/<?php echo hh_h($h['slug']); ?>">

    <?php if ($img): ?><figure class="card-media"><img src="<?php echo hh_h($img); ?>" alt="<?php echo hh_h($name); ?>" loading="lazy"></figure><?php endif; ?>

    <div class="card-body">

      <div class="card-title-row">
        <?php if ($brandLogo): ?>
        <img class="card-brand-logo" src="<?php echo hh_h($brandLogo); ?>" alt="<?php echo hh_h($h['brand']); ?>" loading="lazy">
        <?php endif; ?>
        <h3 class="card-title"><?php echo hh_h($name); ?></h3>
      </div>

      <div class="card-meta">
        <?php if ($screenSize !== '' || $screenRatio !== ''): ?>
        <div class="card-tags">
          <?php if ($screenSize !== ''): ?><span class="card-tag"><?php echo hh_h($screenSize); ?></span><?php endif; ?>
          <?php if ($screenRatio !== ''): ?><span class="card-tag"><?php echo hh_h($screenRatio); ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($h['release_date'])): ?>
        <div class="card-tags card-tags-release">
          <span class="card-tag"><?php echo hh_h(hh_public_ui($locale, 'release_date_label') . $h['release_date']); ?></span>
        </div>
        <?php endif; ?>
      </div>

    </div>

  </a>

  </article>

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

