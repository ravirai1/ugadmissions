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
- **Scrutiny** (department faculty for academic, one record per course choice; religious scrutiny staff, one record per Christian applicant): staff never change what the applicant entered. For each item (CUET papers counted, Class 12 marks; sub-category, CST, baptism, church) they tick OK or type the value they found in the document; these verified values are kept separately and used for the merit list. Staff can approve (once every item is verified) or mark for review with a comment. The religious team sees only basic and religious details.
- **TFA Admin** (separate login, role "TFA Admin"): the final scrutiny authority and the only role that can reject (with a final comment). Scrutiny Overview shows applications received, completed, pending and marked for review for both streams, by course, with all comments. The TFA admin can reopen any decision.
- **Helpdesk** (helpdesk, admin): chats from logged-in applicants and queries from the public form at `/help/query`; replies are also emailed.
- **Print Desk** (print, admin): application forms (single or a whole course), receipts and documents; every print goes in the print log.
- **Interviews** (step 5): the admin issues call letters with a QR code per course; the attendance desk (tech team login) scans the QR code (or searches), checks the candidate's photo and details, and marks present or absent; each department nominee (interview panel login) opens the candidate's profile and enters marks out of 15 for their department's courses and the average is used. The admin finalises marks per course and sees present / absent / not marked by course and date on Call Letters → Attendance summary.
- **Merit and seats**: total 100 = 85 from the verified CUET score (verified score / (papers counted x `CUET_PAPER_MAX`, default 250) x 85) + 15 from the interview. Ties go to higher CUET part, then higher Class 12 %, then the older applicant. Seats are set per course and category on the Seat Matrix page; PwBD seats are supernumerary (extra), by default 5% of a course's seats rounded up, or a number set per course. Seat allotment fills General seats first, then each category, then CST (Christian Scheduled Tribe applicants), then the PwBD extra seats, and gives a candidate selected in several courses the seat in their first DU preference. Candidates holding an offer move up in later rounds if a seat opens in a course they ranked higher (an accepted seat stays confirmed). Offers not accepted by the deadline lapse (checked every 15 minutes) and the seat goes into the next round.
- **Notices** (admin): shown on the home page and dashboards by audience; each notice can be emailed once to its audience.

## Production checklist
- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS only, `SESSION_SECURE_COOKIE=true`
- `expose_php = Off` in php.ini; deny web access to everything outside `public/`
- Email goes through **Amazon SES** over SMTP. In `.env` fill in `MAIL_USERNAME` and `MAIL_PASSWORD` with the SES SMTP credentials (SES console → SMTP settings → Create SMTP credentials), set `MAIL_HOST` to your SES region's endpoint (default `email-smtp.ap-south-1.amazonaws.com`), and verify the `MAIL_FROM_ADDRESS` domain in SES. Request production access so SES can email applicants (the sandbox only sends to verified addresses). Test with `php artisan tinker --execute="Mail::raw('Test', fn (\$m) => \$m->to('you@ststephens.edu')->subject('SES test'));"`
- Uploaded documents (photos, marksheets, helpdesk screenshots, the prospectus) go to an **Amazon S3** bucket. Create a **private** bucket (Block all public access on), an IAM user that can only `s3:GetObject`, `s3:PutObject` and `s3:DeleteObject` on that bucket, then in `.env` set `DOCUMENTS_DISK=s3` and fill `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` (default `ap-south-1`) and `AWS_BUCKET`. Files are always served through the portal after a permission check, never by public S3 links. Without `DOCUMENTS_DISK=s3` files stay in `storage/app/private`.
- Application numbers look like `UGADM2700001` (prefix in `config/admissions.php`, `application_no_prefix`). One CUET application number can register only once.
- Admin → **Prospectus** uploads the prospectus PDF; the website shows it as a page-turning book at `/prospectus` with a download button. Admin → **Notices**: tick "Show in the scrolling notice bar" to put a public notice in the bar at the top of every website page.
- Cron every minute: `* * * * * cd /path/to/app && php artisan schedule:run` (re-checks unconfirmed SBI ePay payments every 15 minutes)
- Queue worker kept running (Supervisor or systemd): `php artisan queue:work --tries=3` (sends notice emails to applicants in the background)
- SBI ePay: set `SBI_EPAY_DRIVER=sbi`, merchant id, key and cipher from SBI's kit; give SBI the return URL `https://<your-domain>/payment/sbi/response` and push URL `https://<your-domain>/payment/sbi/push`
- Daily database backups
- **Security** (see `config/security.php`):
  - After **every deploy** run `php artisan security:integrity --baseline`. The scheduler then compares all code files (including `public/index.php`) with that version every 5 minutes and emails the super admins if anything changed, was added or deleted, or if a PHP/script file appears in `public/` or `storage/`. Status is shown in Admin → Security → Attack Attempts.
  - Security alert emails go to every active super admin, or to `SECURITY_ALERT_EMAILS` (comma separated). They are sent for: an IP blocked after attack attempts, a login lockout, an upload with hidden PHP/script code, a payment amount or merchant mismatch, a duplicate payment, and changed website files.
  - Requests that look like SQL injection, script injection, path probing or attack tools are refused (403) and saved; 5 in 60 minutes blocks the IP from the whole site for 24 hours (`SECURITY_BLOCK_AFTER`, `SECURITY_BLOCK_WINDOW`, `SECURITY_BLOCK_MINUTES`). Unblock under Blocked IPs.
  - Apache: `public/.htaccess` already refuses any `.php` file other than `index.php` and all hidden files. On **nginx** add the same: `location ~ /\.(?!well-known) { deny all; }` and `location ~ \.php$ { if ($uri != "/index.php") { return 403; } ... }`.
  - Applicant and staff activity logs (separate tables) are under Admin → Security; they and attack records are pruned after `ACTIVITY_LOG_DAYS` (default 400).
  - The DU CSAS portal link shown to applicants is `DU_CSAS_URL` (default `https://admission.uod.ac.in`).

## Tests
```bash
php artisan test
```

## Template credits
Weldork by HTML Codex (CC BY 4.0, footer credit kept as the licence requires). Kaiadmin Lite by ThemeKita (MIT).
