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
