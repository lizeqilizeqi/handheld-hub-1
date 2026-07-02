-- Batch translate jobs and live logs
CREATE TABLE IF NOT EXISTS hh_translate_jobs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  status ENUM('running','done','failed') NOT NULL DEFAULT 'running',
  total_count INT UNSIGNED NOT NULL DEFAULT 0,
  current_index INT UNSIGNED NOT NULL DEFAULT 0,
  ok_count INT UNSIGNED NOT NULL DEFAULT 0,
  fail_count INT UNSIGNED NOT NULL DEFAULT 0,
  handheld_ids JSON NOT NULL,
  message TEXT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_status (status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hh_translate_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT UNSIGNED NOT NULL,
  handheld_id INT UNSIGNED NULL,
  slug VARCHAR(128) NOT NULL DEFAULT '',
  level ENUM('info','fetch','ok','error') NOT NULL DEFAULT 'info',
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_job_id (job_id, id),
  CONSTRAINT fk_translate_log_job FOREIGN KEY (job_id) REFERENCES hh_translate_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
