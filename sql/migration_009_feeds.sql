-- RSS feed sources and ingested items (news vertical)

CREATE TABLE IF NOT EXISTS hh_feed_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_code VARCHAR(32) NOT NULL DEFAULT 'news',
    name VARCHAR(128) NOT NULL DEFAULT '',
    feed_url VARCHAR(512) NOT NULL,
    homepage_url VARCHAR(512) NOT NULL DEFAULT '',
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    fetch_interval_minutes INT NOT NULL DEFAULT 360,
    last_fetched_at DATETIME NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hh_feed_sources_site (site_code, is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hh_feed_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_id INT UNSIGNED NOT NULL,
    guid_hash CHAR(64) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    title VARCHAR(512) NOT NULL DEFAULT '',
    link VARCHAR(1024) NOT NULL DEFAULT '',
    summary TEXT NULL,
    author VARCHAR(255) NULL,
    published_at DATETIME NULL,
    status ENUM('pending', 'published', 'hidden') NOT NULL DEFAULT 'pending',
    fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hh_feed_items_guid (guid_hash),
    UNIQUE KEY uq_hh_feed_items_slug (slug),
    KEY idx_hh_feed_items_pub (status, published_at),
    KEY idx_hh_feed_items_source (source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
