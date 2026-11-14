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

Desks in the panel:
- **Helpdesk** (helpdesk, admin): chats from logged-in applicants and queries from the public form at `/help/query`; replies are also emailed.
- **Print Desk** (print, admin): application forms (single or a whole course), receipts and documents; every print goes in the print log.
- **Interviews** (step 5): the admin issues call letters with a QR code per course; the attendance desk scans the QR code (or searches) and marks present or absent; each interview panel member enters marks out of 15 for their department's courses and the average is used. The admin finalises marks per course.
- **Merit and seats**: total 100 = 85 from the verified CUET score (verified score / (papers counted x `CUET_PAPER_MAX`, default 250) x 85) + 15 from the interview. Ties go to higher CUET part, then higher Class 12 %, then the older applicant. Seats are set per course and category on the Seat Matrix page; PwBD seats are supernumerary (extra), by default 5% of a course's seats rounded up, or a number set per course. Seat allotment fills General seats first, then each category, then the PwBD extra seats, and gives a candidate selected in several courses the seat in their first DU preference. Candidates holding an offer move up in later rounds if a seat opens in a course they ranked higher (an accepted seat stays confirmed). Offers not accepted by the deadline lapse (checked every 15 minutes) and the seat goes into the next round.
- **Notices** (admin): shown on the home page and dashboards by audience; each notice can be emailed once to its audience.

## Production checklist
- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS only, `SESSION_SECURE_COOKIE=true`
- `expose_php = Off` in php.ini; deny web access to everything outside `public/`
- Set `MAIL_MAILER=smtp` with the college mail server
- Cron every minute: `* * * * * cd /path/to/app && php artisan schedule:run` (re-checks unconfirmed SBI ePay payments every 15 minutes)
- Queue worker kept running (Supervisor or systemd): `php artisan queue:work --tries=3` (sends notice emails to applicants in the background)
- SBI ePay: set `SBI_EPAY_DRIVER=sbi`, merchant id, key and cipher from SBI's kit; give SBI the return URL `https://<your-domain>/payment/sbi/response` and push URL `https://<your-domain>/payment/sbi/push`
- Daily database backups

## Tests
```bash
php artisan test
```

## Template credits
Weldork by HTML Codex (CC BY 4.0, footer credit kept as the licence requires). Kaiadmin Lite by ThemeKita (MIT).
