-- Handheld Hub — initial schema
CREATE DATABASE IF NOT EXISTS handheld_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE handheld_hub;

CREATE TABLE IF NOT EXISTS hh_admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hh_admin_login_throttle (
  throttle_key CHAR(64) NOT NULL,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_attempt DATETIME NULL,
  PRIMARY KEY (throttle_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: admin / password (change after first login)
INSERT INTO hh_admin_users (username, password_hash)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE username = username;

CREATE TABLE IF NOT EXISTS hh_handhelds (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(128) NOT NULL,
  brand VARCHAR(128) NOT NULL DEFAULT '',
  name_zh VARCHAR(255) NOT NULL DEFAULT '',
  name_en VARCHAR(255) NOT NULL DEFAULT '',
  release_year SMALLINT UNSIGNED NULL,
  release_month TINYINT UNSIGNED NULL,
  release_date DATE NULL,
  screen_size VARCHAR(64) NOT NULL DEFAULT '',
  screen_ratio VARCHAR(64) NOT NULL DEFAULT '',
  source_url VARCHAR(512) NOT NULL DEFAULT '',
  source_scraped_at DATETIME NULL,
  content_hash CHAR(64) NOT NULL DEFAULT '',
  status ENUM('draft','review','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_slug (slug),
  KEY idx_release (release_date DESC, id DESC),
  KEY idx_brand (brand),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hh_handheld_specs (
  handheld_id INT UNSIGNED NOT NULL,
  specs_json JSON NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (handheld_id),
  CONSTRAINT fk_specs_handheld FOREIGN KEY (handheld_id) REFERENCES hh_handhelds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hh_handheld_content (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  handheld_id INT UNSIGNED NOT NULL,
  locale ENUM('zh','en') NOT NULL,
  title VARCHAR(512) NOT NULL DEFAULT '',
  summary TEXT NULL,
  body_html MEDIUMTEXT NULL,
  meta_description VARCHAR(512) NOT NULL DEFAULT '',
  review_status ENUM('pending','ai_draft','human_approved') NOT NULL DEFAULT 'pending',
  verified_urls JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_handheld_locale (handheld_id, locale),
  KEY idx_review (review_status),
  CONSTRAINT fk_content_handheld FOREIGN KEY (handheld_id) REFERENCES hh_handhelds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hh_handheld_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  handheld_id INT UNSIGNED NOT NULL,
  path VARCHAR(512) NOT NULL,
  source_url VARCHAR(512) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  is_cover TINYINT(1) NOT NULL DEFAULT 0,
  alt_text_zh VARCHAR(255) NOT NULL DEFAULT '',
  alt_text_en VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_handheld_sort (handheld_id, sort_order),
  CONSTRAINT fk_images_handheld FOREIGN KEY (handheld_id) REFERENCES hh_handhelds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hh_blogger_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  handheld_id INT UNSIGNED NOT NULL,
  locale ENUM('en','zh') NOT NULL DEFAULT 'en',
  blogger_post_id VARCHAR(64) NOT NULL DEFAULT '',
  blogger_url VARCHAR(512) NOT NULL DEFAULT '',
  sync_status ENUM('draft','scheduled','published','error') NOT NULL DEFAULT 'draft',
  scheduled_at DATETIME NULL,
  published_at DATETIME NULL,
  last_error TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_handheld_locale (handheld_id, locale),
  KEY idx_sync (sync_status),
  CONSTRAINT fk_blogger_handheld FOREIGN KEY (handheld_id) REFERENCES hh_handhelds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hh_scrape_jobs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_type ENUM('full','incremental','single') NOT NULL DEFAULT 'incremental',
  status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
  total_pages INT UNSIGNED NOT NULL DEFAULT 0,
  current_page INT UNSIGNED NOT NULL DEFAULT 0,
  items_found INT UNSIGNED NOT NULL DEFAULT 0,
  items_new INT UNSIGNED NOT NULL DEFAULT 0,
  items_updated INT UNSIGNED NOT NULL DEFAULT 0,
  items_failed INT UNSIGNED NOT NULL DEFAULT 0,
  message TEXT NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hh_scrape_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT UNSIGNED NULL,
  level ENUM('info','warn','error') NOT NULL DEFAULT 'info',
  slug VARCHAR(128) NOT NULL DEFAULT '',
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_job (job_id, created_at),
  CONSTRAINT fk_log_job FOREIGN KEY (job_id) REFERENCES hh_scrape_jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hh_blogger_oauth (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  refresh_token TEXT NULL,
  access_token TEXT NULL,
  token_expires_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
