# St. Stephen's College – UG Admissions 2027

Laravel 13 admissions portal: applicant website (Weldork theme) and staff panel (Kaiadmin theme).
See [docs/PLAN.md](docs/PLAN.md) for the full plan and progress.

## Requirements
PHP 8.3+, Composer, MySQL 8 (or MariaDB 10.6+), a mail server (SMTP) for verification and OTP emails.

## Setup
```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# edit .env: APP_URL, DB_*, MAIL_*, ADMIN_PATH (pick a hard-to-guess staff URL)
php artisan migrate --force
php artisan admin:create-super        # the only way to create a super admin
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
Point the web server's document root at `public/`.

## Staff panel
`https://<your-domain>/<ADMIN_PATH>/login` (default `ssc-staff`). Login needs password, CAPTCHA and an emailed 6-digit code.

## Production checklist
- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS only, `SESSION_SECURE_COOKIE=true`
- `expose_php = Off` in php.ini; deny web access to everything outside `public/`
- Set `MAIL_MAILER=smtp` with the college mail server
- Cron every minute: `* * * * * cd /path/to/app && php artisan schedule:run` (re-checks unconfirmed SBI ePay payments every 15 minutes)
- SBI ePay: set `SBI_EPAY_DRIVER=sbi`, merchant id, key and cipher from SBI's kit; give SBI the return URL `https://<your-domain>/payment/sbi/response` and push URL `https://<your-domain>/payment/sbi/push`
- Daily database backups

## Tests
```bash
php artisan test
```

## Template credits
Weldork by HTML Codex (CC BY 4.0, footer credit kept as the licence requires). Kaiadmin Lite by ThemeKita (MIT).
