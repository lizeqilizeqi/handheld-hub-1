-- Hub multi-site registry (admin + future dynamic hub sections)

CREATE TABLE IF NOT EXISTS hh_sites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    name_en VARCHAR(128) NOT NULL DEFAULT '',
    name_zh VARCHAR(128) NOT NULL DEFAULT '',
    base_url VARCHAR(255) NOT NULL,
    hosts_json TEXT NOT NULL,
    status ENUM('live', 'soon', 'hidden') NOT NULL DEFAULT 'soon',
    sort_order INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hh_sites_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hh_hub_sections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_code VARCHAR(32) NOT NULL,
    title_en VARCHAR(128) NOT NULL DEFAULT '',
    title_zh VARCHAR(128) NOT NULL DEFAULT '',
    desc_en TEXT NULL,
    desc_zh TEXT NULL,
    entry_path VARCHAR(255) NOT NULL DEFAULT '',
    badge ENUM('live', 'soon') NOT NULL DEFAULT 'soon',
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hh_hub_sections_site (site_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO hh_sites (code, name_en, name_zh, base_url, hosts_json, status, sort_order) VALUES
('hub', 'Old Man Hub', '老男人 Hub', 'https://www.oldmanhub.com', '["www.oldmanhub.com","oldmanhub.com"]', 'live', 0),
('handhelds', 'Handhelds', '掌机百科', 'https://handhelds.oldmanhub.com', '["handhelds.oldmanhub.com"]', 'live', 10),
('game', 'Retro Games', '怀旧游戏', 'https://game.oldmanhub.com', '["game.oldmanhub.com"]', 'soon', 20),
('news', 'News & Picks', '资讯精选', 'https://news.oldmanhub.com', '["news.oldmanhub.com"]', 'soon', 30)
ON DUPLICATE KEY UPDATE
    name_en = VALUES(name_en),
    name_zh = VALUES(name_zh),
    base_url = VALUES(base_url),
    hosts_json = VALUES(hosts_json),
    status = VALUES(status),
    sort_order = VALUES(sort_order);

INSERT INTO hh_hub_sections (site_code, title_en, title_zh, desc_en, desc_zh, entry_path, badge, sort_order) VALUES
('handhelds', 'Handheld encyclopedia', '掌机百科',
 'Specs, release timelines, and editorial guides for portable consoles.',
 '掌机规格、发布时间与英文介绍，独立子站持续更新。',
 '/en', 'live', 10),
('game', 'Retro games', '怀旧游戏',
 'Classic platforms and titles — curated directory (coming soon).',
 '经典平台与游戏目录筹备中，以合规资料与外链为主。',
 '/', 'soon', 20),
('news', 'News & picks', '资讯精选',
 'Hardware, indie retro, and culture — feed aggregation in progress.',
 '硬件、独立复古与文化向内容，后台抓取接入中。',
 '/', 'soon', 30);
