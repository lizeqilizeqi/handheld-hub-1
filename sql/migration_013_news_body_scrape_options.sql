-- Full article body + scrape job options; wipe news items for clean re-scrape.

ALTER TABLE hh_scrape_jobs
  ADD COLUMN options_json TEXT NULL AFTER message;

ALTER TABLE hh_feed_items
  ADD COLUMN body_zh MEDIUMTEXT NULL AFTER summary_en,
  ADD COLUMN body_en MEDIUMTEXT NULL AFTER body_zh;

DELETE FROM hh_feed_items;
