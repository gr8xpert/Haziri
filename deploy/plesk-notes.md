# Plesk setup (spec §14)

1. **Subscription** `hazri.pk`, document root `httpdocs/public`. Add subdomain `*` (wildcard) plus `admin`, `adms`, `api`
   pointing at the same document root.
2. **PHP 8.4 FPM** (required: spatie/laravel-activitylog 5, Pest 5). `memory_limit=512M`, `max_execution_time=120`,
   `upload_max_filesize=20M`, `post_max_size=24M`. Extensions: pdo_mysql, bcmath, intl, zip, gd, exif, pcntl.
3. **Wildcard TLS** needs DNS-01: host DNS in Plesk (Let's Encrypt extension, wildcard option) or `acme.sh --dns dns_cf`
   with the Cloudflare API. ZKTeco devices must reach `https://adms.hazri.pk` (some firmwares only speak port 80 — keep
   HTTP open on `adms.` or enable "HTTPS" on the device). Custom company domains: CNAME to `{slug}.hazri.pk`, then add the
   domain to the subscription's certificate.
4. **MySQL/MariaDB**: app user with `CREATE DATABASE` and `GRANT OPTION` (it creates `hazri_t_*` and grants the AI user);
   read-only user for Ask Haziri: `CREATE USER 'hazri_ai'@'localhost' IDENTIFIED BY '…';` — grants are added per tenant by
   provisioning and `php artisan hazri:ai:grant-readonly`. Raise `max_connections` for many tenant DBs.
5. **Cron**: see `cron.txt` (scheduler + queue worker every minute, nightly backup).
6. **Storage**: `php artisan storage:link`; tenant files live under `storage/tenant{id}/app/…` (stancl filesystem bootstrapper).
7. **Firewall**: inbound 80/443 only.
8. **.env** (production): `APP_ENV=production`, `APP_DEBUG=false`, `HAZRI_CENTRAL_DOMAIN=hazri.pk`, `SESSION_SECURE_COOKIE=true`,
   `MAIL_*` (Plesk SMTP), `ANTHROPIC_API_KEY`, `AI_DB_READONLY_*`, `HAZRI_PAYMENT_INSTRUCTIONS`.
9. **Dedicated instance**: same steps on the client's VPS, `.env` `HAZRI_MODE=dedicated`, `HAZRI_DEDICATED_TENANT={slug}`,
   restore the tenant DB dump + one central tenant row. No code differences.

## Real visitor IP (kiosk / mobile office-internet rules)

Plesk puts nginx in front of PHP. Set `TRUSTED_PROXIES=127.0.0.1` in `.env` so the app sees the visitor's real IP;
otherwise every request looks like it comes from 127.0.0.1 and the "office internet only" kiosk and branch rules misfire.

## White-label neutral domain

Register a domain with no platform name (e.g. `attendance-cloud.pk`) and point it, plus `adms.` and `api.` subdomains and
a wildcard for client CNAMEs, at this server with TLS. Set `HAZRI_NEUTRAL_DOMAIN=attendance-cloud.pk`. White-label clients
CNAME their own domain (e.g. `attendance.aasml.com`) to it; issue a certificate for each client domain.
