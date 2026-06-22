# OJMAS Deployment Checklist

## Environment

- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Set `APP_URL=https://ojmaspublish.online`
- Set a real `APP_KEY`
- Set valid `MAIL_*` settings
- Set valid `PAYSTACK_SECRET_KEY` and related payment settings
- Use `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, and `MAIL_SCHEME=smtp`
- Set `MAIL_EHLO_DOMAIN=ojmaspublish.online`
- Use [.env.production.example](C:/xampp/htdocs/ojmas-journal/.env.production.example) as the live server starting point

## Security

- Serve the site behind HTTPS only
- Enable secure cookies in `.env`:
  - `SESSION_SECURE_COOKIE=true`
  - `SESSION_HTTP_ONLY=true`
  - `SESSION_SAME_SITE=lax`
- Use a non-local session/cache/queue backend in production when available:
  - `SESSION_DRIVER=database` or `redis`
  - `CACHE_STORE=database` or `redis`
  - `QUEUE_CONNECTION=database` or `redis`
- Make sure the `notifications`, `jobs`, and `cache` tables are migrated if using database-backed drivers
- Restrict write permissions to:
  - `storage/`
  - `bootstrap/cache/`
- Keep `.env` outside public exposure

## Laravel Commands

Run these during deployment:

```powershell
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
php artisan up
```

For this app, make sure the web server points to `public/` and that `https://ojmaspublish.online/webhooks/paystack` is registered as the Paystack webhook URL.

## Apache / .htaccess

- Preferred setup:
  - point Apache `DocumentRoot` directly to the app's `public/` directory
  - keep `AllowOverride All` enabled for the site so Laravel rewrites work
- This repository now includes:
  - root [`.htaccess`](C:/xampp/htdocs/ojmas-journal/.htaccess) for hosts that still point Apache at the project root
  - [public/.htaccess](C:/xampp/htdocs/ojmas-journal/public/.htaccess) for the preferred `DocumentRoot=public/` setup
- Both rewrite files now:
  - force `https://ojmaspublish.online`
  - redirect `www.ojmaspublish.online` to the bare domain
  - preserve normal Laravel route handling

Example Apache vhost:

```apache
<VirtualHost *:80>
    ServerName ojmaspublish.online
    ServerAlias www.ojmaspublish.online
    DocumentRoot /path/to/ojmas-journal/public

    <Directory /path/to/ojmas-journal/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Full example file:
- [deploy/apache/ojmaspublish.online.conf.example](C:/xampp/htdocs/ojmas-journal/deploy/apache/ojmaspublish.online.conf.example)

## Background Workers

- Start a queue worker if notifications or future background jobs are expected:

```powershell
php artisan queue:work --tries=3 --timeout=120
```

- Add a scheduler if scheduled tasks are introduced later:

```powershell
php artisan schedule:run
```

## Final Smoke Checks

- Register a new user and confirm email verification mail is sent
- Confirm verification links and password reset links point to `https://ojmaspublish.online`
- Verify login/logout
- Submit a manuscript
- Complete a payment and confirm:
  - payment record is marked paid
  - receipt is generated
  - admin dashboard shows the payment
- Confirm private file downloads require auth
- Confirm Paystack callback and webhook URLs use `https://ojmaspublish.online`

## Rollback Notes

- Keep a database backup before migration
- Keep the previous release available for fast rollback
- If deployment fails after cache changes, run:

```powershell
php artisan optimize:clear
```
