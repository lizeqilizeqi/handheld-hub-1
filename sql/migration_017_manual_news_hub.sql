-- Wipe scraped news; enable manual editorial posts + hub module toggles baseline.

DELETE FROM hh_feed_items;

ALTER TABLE hh_feed_items
  ADD COLUMN item_kind ENUM('manual','scraped') NOT NULL DEFAULT 'scraped' AFTER source_id;

UPDATE hh_feed_sources SET is_enabled = 0 WHERE feed_url LIKE '%mydrivers%' OR feed_url LIKE '%zhangjiyouxi%';

INSERT INTO hh_feed_sources (site_code, name, feed_url, homepage_url, source_kind, is_enabled, fetch_interval_minutes)
SELECT 'news', '本站原创', '', 'https://www.oldmanhub.com', 'rss', 0, 10080
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM hh_feed_sources WHERE site_code = 'news' AND name = '本站原创' LIMIT 1);

UPDATE hh_sites SET status = 'live' WHERE code IN ('news', 'game', 'handhelds');

UPDATE hh_hub_sections SET badge = 'live', is_visible = 1 WHERE site_code IN ('handhelds', 'game', 'news');
