-- Retro game catalog entries (metadata + links only, no ROM hosting)

CREATE TABLE IF NOT EXISTS hh_game_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(128) NOT NULL,
    platform VARCHAR(64) NOT NULL DEFAULT '',
    title_en VARCHAR(255) NOT NULL DEFAULT '',
    title_zh VARCHAR(255) NOT NULL DEFAULT '',
    summary_en TEXT NULL,
    summary_zh TEXT NULL,
    body_en TEXT NULL,
    body_zh TEXT NULL,
    external_url VARCHAR(512) NOT NULL DEFAULT '',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hh_game_entries_slug (slug),
    KEY idx_hh_game_entries_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
