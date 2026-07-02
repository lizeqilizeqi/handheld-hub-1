# Handheld Hub

PHP + MySQL CMS for handheld gaming devices. Scrapes [掌机圈](https://zhangjiquan.com/handhelds) as primary data source, supports DeepSeek English translation, public EN/ZH site, and Google Blogger publishing.

## Quick start (Docker)

```bash
cd handheld-hub
copy config.example.php config.local.php
# Edit config.local.php — set mysql host to db for Docker:
# dsn => mysql:host=db;port=3306;dbname=handheld_hub;charset=utf8mb4

docker compose up -d
```

- Public site: http://localhost:8080/en/handhelds
- Admin: http://localhost:8080/admin/ (login `admin` / `password`)

## Quick start (Windows PHP built-in server)

1. Install PHP 8.3+ with extensions: `pdo_mysql`, `curl`, `dom`, `mbstring`
2. Create MySQL database and run `sql/migration_001_handhelds.sql`
3. Copy `config.example.php` → `config.local.php` and set MySQL credentials
4. Run:

```powershell
cd "D:\cursor\google blog\1\handheld-hub"
php -S localhost:8080 -t public public/index.php
```

Admin (separate terminal or map via nginx in production):

```powershell
php -S localhost:8081 -t admin
```

## Workflow

1. **Scrape** — Admin → Scrape Sync, or `php bin/scrape.php --mode=incremental`
2. **Translate** — Admin → Translate → DeepSeek generates EN draft (set `deepseek.api_key`)
3. **Review** — Admin → Handhelds → edit EN content → set review to `human_approved`
4. **Publish site** — Admin → 独立站发布 → preview → publish (sets status `published` on public /zh/ and /en/)
5. **Blogger** — Configure OAuth in `config.local.php`, connect Google, publish from Admin → Blogger

## Config (`config.local.php`)

| Key | Purpose |
|-----|---------|
| `app.base_url` | Public site URL (for images & canonical links) |
| `mysql.*` | Database connection |
| `deepseek.api_key` | DeepSeek translation |
| `blogger.client_id/secret/redirect_uri/blog_id` | Google Blogger API |

## Deploy to Google Cloud / Ubuntu

1. Push this repo to GitHub/GitLab.
2. Local admin → **服务器部署** — copy the two SSH commands.
3. GCP Console → VM → **SSH** → paste and run.

On the server, updates are one command:

```bash
sudo bash /opt/handheld-hub/deploy/server-deploy.sh
```

See also `deploy/nginx-site.conf` if you later put nginx + HTTPS in front of Docker.

## CLI

```bash
php bin/scrape.php --mode=incremental
php bin/scrape.php --mode=full
php bin/scrape.php --slug=rg-rotate
```

## Default admin

- Username: `admin`
- Password: `password` — change `password_hash` in `hh_admin_users` after first login.

Generate hash: `php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"`
