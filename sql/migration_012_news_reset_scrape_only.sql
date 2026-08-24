-- One-time reset: remove RSS trial items; news only enters DB via 抓取同步 (html_scrape).
-- Safe to run after 009/011. Re-running DELETE is OK; ADD COLUMN may fail if already applied — ignore duplicate column error.

ALTER TABLE hh_feed_sources
  ADD COLUMN source_kind ENUM('rss','html_scrape') NOT NULL DEFAULT 'rss' AFTER homepage_url;

DELETE FROM hh_feed_items;

UPDATE hh_feed_sources SET is_enabled = 0;

UPDATE hh_feed_sources SET source_kind = 'html_scrape', is_enabled = 0
  WHERE feed_url LIKE '%zhangjiyouxi.html%';

DELETE FROM hh_feed_sources WHERE feed_url NOT LIKE '%zhangjiyouxi.html%';

INSERT INTO hh_feed_sources (site_code, name, feed_url, homepage_url, source_kind, is_enabled, fetch_interval_minutes)
SELECT 'news', '快科技-掌机游戏列表', 'https://news.mydrivers.com/zhangjiyouxi.html', 'https://news.mydrivers.com', 'html_scrape', 0, 10080
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM hh_feed_sources WHERE feed_url LIKE '%zhangjiyouxi.html%' LIMIT 1);
