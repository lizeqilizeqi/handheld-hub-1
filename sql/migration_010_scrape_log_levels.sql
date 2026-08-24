-- Scrape log levels used by lib/scraper/scraper_service.php and admin/scrape.php UI

ALTER TABLE hh_scrape_logs
  MODIFY COLUMN level ENUM('info','warn','error','fetch','ok','skip') NOT NULL DEFAULT 'info';
