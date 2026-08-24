# Phase 0 — Hub (www) + Handhelds subdomain

## Caddyfile (replace handheld-only redirect block)

```caddy
www.oldmanhub.com {
    reverse_proxy 127.0.0.1:8080
}

handhelds.oldmanhub.com {
    reverse_proxy 127.0.0.1:8080
}

oldmanhub.com {
    redir https://www.oldmanhub.com{uri} permanent
}
```

Remove any rule that 301s `www.oldmanhub.com` → `handhelds.oldmanhub.com`.

Reload:

```bash
sudo systemctl reload caddy
```

## Server config.local.php / config.secrets.php

Ensure (merge into existing files, do not overwrite secrets):

```php
'app' => array(
    'base_url' => 'https://www.oldmanhub.com',
    'sites' => array(
        'hub' => array(
            'hosts' => array('www.oldmanhub.com', 'oldmanhub.com'),
            'base_url' => 'https://www.oldmanhub.com',
        ),
        'handhelds' => array(
            'hosts' => array('handhelds.oldmanhub.com'),
            'base_url' => 'https://handhelds.oldmanhub.com',
        ),
    ),
    'legacy_hosts' => array(
        'oldman.dpdns.org' => 'https://www.oldmanhub.com',
        'www.oldman.dpdns.org' => 'https://www.oldmanhub.com',
    ),
),
```

## Cloudflare DNS

| Type | Name | Content | Proxy |
|------|------|---------|-------|
| A | `@` | VM IP | Proxied |
| A | `www` | VM IP | Proxied |
| A | `handhelds` | VM IP | Proxied |

## Phase 1 — SEO index + admin sites

Upload files (see list in chat), then on server:

```bash
cd /opt/handheld-hub
docker compose -f docker-compose.prod.yml exec -T db mysql -u handheld -phandheld handheld_hub < sql/migration_007_hub_sites.sql
```

Verify:

- https://www.oldmanhub.com/llms.txt
- https://www.oldmanhub.com/sitemap-index.xml
- https://www.oldmanhub.com/admin/sites.php

## Phase 2 — DB hub sections + game subdomain stub

### Cloudflare DNS

| Type | Name | Content | Proxy |
|------|------|---------|-------|
| A | `game` | VM IP | Proxied |

(`news` can wait until Phase 3.)

### Caddyfile (add block)

```caddy
game.oldmanhub.com {
    reverse_proxy 127.0.0.1:8080
}
```

Reload: `sudo systemctl reload caddy`

### Server `config.local.php` (merge)

Add under `app.sites`:

```php
'game' => array(
    'hosts' => array('game.oldmanhub.com'),
    'base_url' => 'https://game.oldmanhub.com',
    'status' => 'soon',
),
```

No new SQL migration (uses existing `hh_hub_sections` from migration 007).

### Upload (Phase 2)

- `lib/site_repo.php`
- `lib/site_context.php`
- `lib/vertical_ui.php`
- `lib/vertical_layout.php`
- `public/index.php`
- `public/hub_home.php`
- `public/game_home.php`
- `public/assets/style.css`
- `config.example.php` (reference only)

**Do not overwrite** server-custom `hub_home.php` / Hub CSS unless you want repo-driven sections on www.

### Verify

- https://www.oldmanhub.com/en — section cards match `hh_hub_sections` (admin → Sites)
- https://game.oldmanhub.com/en — bilingual “coming soon” placeholder
- https://game.oldmanhub.com/llms.txt — 404 (hub-only)

## Phase 3 — news subdomain + admin hub sections + SEO index

### Cloudflare DNS

| Type | Name | Content | Proxy |
|------|------|---------|-------|
| A | `news` | VM IP | Proxied |

### Caddyfile

```caddy
news.oldmanhub.com {
    reverse_proxy 127.0.0.1:8080
}
```

Reload: `sudo systemctl reload caddy`

### Server `config.local.php` (merge)

```php
'news' => array(
    'hosts' => array('news.oldmanhub.com'),
    'base_url' => 'https://news.oldmanhub.com',
    'status' => 'soon',
),
```

No new SQL migration.

### Upload (Phase 3)

- `lib/site_context.php`
- `lib/site_repo.php`
- `lib/vertical_ui.php`
- `lib/vertical_layout.php`
- `lib/hub_seo.php`
- `lib/sitemap.php`
- `public/index.php`
- `public/vertical_home.php`
- `public/game_home.php`
- `public/news_home.php`
- `public/assets/style.css`
- `admin/sites.php`
- `admin/i18n.php`
- `admin/assets/admin.css` (sites form styles)
- `deploy/PHASE0-HUB-DEPLOY.md` (optional doc)

**Note:** Server may keep custom `hub_home.php` / Hub CSS; Hub section **copy** still editable via admin when using repo `hub_home.php` + DB sections.

### Verify

- https://news.oldmanhub.com/en and `/zh` — News placeholder (`noindex`)
- https://news.oldmanhub.com/llms.txt — 404
- https://www.oldmanhub.com/sitemap-index.xml — includes `game` + `news` sitemap URLs when in `hh_sites`
- Admin → Sites — edit hub section titles/descriptions and save
- https://game.oldmanhub.com/en — still works (shared `vertical_home.php`)

## Phase 4 — Game catalog MVP (metadata + admin, no ROMs)

### Database (server)

```bash
cd /opt/handheld-hub
docker compose -f docker-compose.prod.yml exec -T db mysql -u handheld -phandheld handheld_hub < sql/migration_008_game_entries.sql
```

### Upload (Phase 4)

- `sql/migration_008_game_entries.sql`
- `lib/game_repo.php`
- `lib/sitemap.php`
- `lib/vertical_layout.php`
- `public/index.php`
- `public/game_home.php`
- `public/game_games.php`
- `public/game_detail.php`
- `public/assets/style.css`
- `admin/games.php`
- `admin/layout.php`
- `admin/i18n.php`
- `admin/assets/admin.css`
- `deploy/PHASE0-HUB-DEPLOY.md` (optional)

### Verify

- Admin → **怀旧游戏**：新建一条 `published` 测试条目
- https://game.oldmanhub.com/en/games — 列表
- https://game.oldmanhub.com/en/game/{slug} — 详情 + 外链
- https://game.oldmanhub.com/sitemap.xml — 含目录与详情 URL
- https://news.oldmanhub.com/en — 仍为 news 占位（`vertical_home.php`，body 应为 `vertical-news`）

**Next (Phase 5):** RSS `hh_feed_sources` + `bin/fetch_feeds.php` for news vertical.

## Phase 5 — News RSS ingestion + public list

### Database

```bash
cd /opt/handheld-hub
docker compose -f docker-compose.prod.yml exec -T db mysql -u handheld -phandheld handheld_hub < sql/migration_009_feeds.sql
```

### Cron (optional)

```cron
0 */6 * * * cd /opt/handheld-hub && docker compose -f docker-compose.prod.yml exec -T web php bin/fetch_feeds.php >> /var/log/handheld-feeds.log 2>&1
```

Or on host if PHP in container only:

```bash
docker compose -f docker-compose.prod.yml exec -T web php bin/fetch_feeds.php
```

### Upload (Phase 5)

- `sql/migration_009_feeds.sql`
- `lib/feed_repo.php`
- `lib/feed_fetcher.php`
- `bin/fetch_feeds.php`
- `lib/sitemap.php`
- `lib/vertical_layout.php`
- `public/index.php`
- `public/news_home.php`
- `public/news_list.php`
- `public/news_detail.php`
- `public/assets/style.css`
- `admin/feeds.php`
- `admin/layout.php`
- `admin/i18n.php`
- `admin/assets/admin.css`

### Verify

- Admin → **资讯列表**：抓取后可见条目；**不再**在后台添加 RSS 源（可选保留 `bin/fetch_feeds.php` 给旧 RSS 源）

## Phase 5c — 清空 RSS 试用数据 + 仅抓取入库

线上若已出现 Time Extension 等英文 RSS 条目，来自 Phase 5 的 `fetch_feeds.php` 或后台 RSS，**不是**快科技抓取。

```bash
cd /opt/handheld-hub
docker compose -f docker-compose.prod.yml exec -T db mysql -u handheld -phandheld handheld_hub < sql/migration_012_news_reset_scrape_only.sql
```

效果：`DELETE` 全部 `hh_feed_items`；删除非快科技源；快科技源标记 `html_scrape` 且 `is_enabled=0`（仅 `bin/scrape.php --channel=news` 写入）。

建议 **关闭或删除** Cron 中的 `bin/fetch_feeds.php`，避免再次拉 RSS。

### Upload (Phase 5c)

- `sql/migration_012_news_reset_scrape_only.sql`
- `lib/scraper/mydrivers_news.php`, `lib/feed_repo.php`, `lib/feed_fetcher.php`
- `bin/fetch_feeds.php`, `admin/feeds.php`, `admin/i18n.php`, `lib/vertical_ui.php`, `public/news_home.php`

## Phase 5d — 资讯滚动列表 + 正文 + 卡片 UI

### Migration（会清空资讯表，重新开始）

```bash
cd /opt/handheld-hub
docker compose -f docker-compose.prod.yml exec -T db mysql -u handheld -phandheld handheld_hub < sql/migration_013_news_body_scrape_options.sql
```

### 抓取

Admin → **抓取同步** → 渠道 **资讯** → **资讯起始日期**（默认当年 1-1）→ 开始。

- 首屏 HTML + 博客 JSONP 分页（与站点无限滚动相同 API）直到遇到早于起始日的文章
- 每条新链接抓取 `.news_info` 正文（含插图 URL），去「快科技」字样，去重 guid
- CLI：`php bin/scrape.php --channel=news --since=2026-01-01`

### 流程

抓取 → **翻译**（标题+摘要+正文 HTML）→ **独立站发布**

### Upload (Phase 5d)

- `sql/migration_013_news_body_scrape_options.sql`
- `lib/scraper/mydrivers_news.php`, `lib/scraper/channel_scraper_service.php`
- `lib/handheld_repo.php`, `lib/scraper/scraper_runner.php`, `lib/content_translate.php`
- `lib/feed_repo.php`, `lib/news_ui.php`
- `admin/scrape.php`, `bin/scrape.php`
- `public/news_home.php`, `public/news_list.php`, `public/news_detail.php`, `public/assets/style.css`
- https://news.oldmanhub.com/en/news
- https://news.oldmanhub.com/en/news/{slug} — 摘要 + 阅读原文（canonical 指向原文）
- `php bin/fetch_feeds.php` / Cron 可重复运行（去重）

## Phase 5b — 资讯 HTML 抓取 + 双语 + 后台列表

### Migrations

```bash
cd /opt/handheld-hub
docker compose -f docker-compose.prod.yml exec -T db mysql -u handheld -phandheld handheld_hub < sql/migration_010_scrape_log_levels.sql
docker compose -f docker-compose.prod.yml exec -T db mysql -u handheld -phandheld handheld_hub < sql/migration_011_news_scrape_bilingual.sql
```

### 抓取（快科技 · 掌机游戏列表）

Admin → **抓取同步** → 渠道 **资讯** → 增量/全量 → 开始。

或 CLI：

```bash
docker compose -f docker-compose.prod.yml exec -T web php bin/scrape.php --channel=news --mode=incremental
```

复古游戏渠道会提示「源未配置」，待后续对话增加数据源。

### 流程

1. **资讯列表** / **复古游戏**：仅表格查看 + 编辑（与掌机列表一致）；RSS 源不在后台 CRUD，由代码/对话维护。
2. **翻译**：Admin → 翻译 →「批量翻译资讯/游戏」；单条可从列表点「用 DeepSeek 生成英文」。
3. **独立站发布**：Admin → 独立站发布 →「一键发布全部可发资讯/游戏」。

### Upload (Phase 5b)

- `sql/migration_010_scrape_log_levels.sql`, `sql/migration_011_news_scrape_bilingual.sql`
- `lib/scraper/mydrivers_news.php`, `lib/scraper/channel_scraper_service.php`, `lib/scraper/scraper_runner.php`
- `lib/handheld_repo.php`, `lib/feed_repo.php`, `lib/content_translate.php`, `lib/publish_service.php`, `lib/game_repo.php`
- `bin/scrape.php`, `admin/scrape.php`, `admin/games.php`, `admin/feeds.php`, `admin/translate.php`, `admin/publish.php`, `admin/i18n.php`
- `public/news_home.php`, `public/news_list.php`, `public/news_detail.php`

### Verify

- Admin → **抓取同步** channel=news 成功，**资讯列表** 有条目
- **翻译** → 批量翻译资讯 → **独立站发布** → 发布资讯
- https://news.oldmanhub.com/en/news 与 `/zh/news` 标题随语言切换
