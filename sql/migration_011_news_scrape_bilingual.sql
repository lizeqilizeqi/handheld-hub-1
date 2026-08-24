-- Scrape channel + bilingual news items + default MyDrivers source (HTML list, not RSS)

ALTER TABLE hh_scrape_jobs
  ADD COLUMN channel VARCHAR(16) NOT NULL DEFAULT 'handheld' AFTER job_type;

ALTER TABLE hh_feed_items
  ADD COLUMN title_zh VARCHAR(512) NOT NULL DEFAULT '' AFTER slug,
  ADD COLUMN title_en VARCHAR(512) NOT NULL DEFAULT '' AFTER title_zh,
  ADD COLUMN summary_zh TEXT NULL AFTER summary,
  ADD COLUMN summary_en TEXT NULL AFTER summary_zh,
  ADD COLUMN image_url VARCHAR(512) NOT NULL DEFAULT '' AFTER link,
  ADD COLUMN translate_status ENUM('pending','done','failed') NOT NULL DEFAULT 'pending' AFTER status;

UPDATE hh_feed_items SET title_zh = title WHERE title_zh = '' AND title <> '';
UPDATE hh_feed_items SET summary_zh = summary WHERE (summary_zh IS NULL OR summary_zh = '') AND summary IS NOT NULL AND summary <> '';

INSERT INTO hh_feed_sources (site_code, name, feed_url, homepage_url, is_enabled, fetch_interval_minutes)
SELECT 'news', '快科技-掌机游戏列表', 'https://news.mydrivers.com/zhangjiyouxi.html', 'https://news.mydrivers.com', 0, 10080
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM hh_feed_sources WHERE feed_url LIKE '%zhangjiyouxi.html%' LIMIT 1);
