<?php

function hh_news_render_list_item(array $row, $locale)
{
    $href = hh_site_public_url($locale . '/news/' . $row['slug'], 'news');
    $title = hh_feed_item_title($row, $locale);
    $sum = hh_feed_item_summary($row, $locale);
    $img = hh_feed_item_image_url($row);
    $date = !empty($row['published_at']) ? substr((string) $row['published_at'], 0, 16) : '';
    ?>
<li class="news-card">
  <a class="news-card-link" href="<?php echo hh_h($href); ?>">
    <?php if ($img !== ''): ?>
    <span class="news-card-thumb"><img src="<?php echo hh_h($img); ?>" alt="" loading="lazy" width="160" height="100"></span>
    <?php endif; ?>
    <span class="news-card-body">
      <span class="news-card-title"><?php echo hh_h($title); ?></span>
      <?php if ($sum !== ''): ?>
      <span class="news-card-summary"><?php echo hh_h(hh_feed_summary_excerpt($sum, 220)); ?></span>
      <?php endif; ?>
      <?php if ($date !== ''): ?>
      <time class="news-card-date" datetime="<?php echo hh_h(substr($date, 0, 10)); ?>"><?php echo hh_h($date); ?></time>
      <?php endif; ?>
    </span>
  </a>
</li>
    <?php
}
