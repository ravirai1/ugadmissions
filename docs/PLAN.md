# UG Admissions 2027 – Project Plan

St. Stephen's College, University of Delhi. Laravel 13 + MySQL. Applicant site uses the Weldork template and the admin panel uses Kaiadmin, both rebranded with the college logo.

## Step 1 – Foundation, accounts and security ✅ (this PR)
- Public pages: Home, About, FAQ, Contact, Documents Required
- Applicant signup: name, email, mobile, CUET 2027 number (must start with 27, unique)
- Email verification with resend, forgot/reset password, applicant dashboard with status tracker
- Separate staff login (own table, guard and URL), emailed OTP second step, forced password change on first login, idle timeout
- Super admin created only from the command line (`php artisan admin:create-super`)
- CSRF on every form, XSS protection (escaped output, strict CSP with nonces, security headers)
- Failed-login IP blocking (staff: 5 in 15 min → 30 min block; applicants: 10 in 15 min → 15 min), login log, manual block/unblock, audit log
- Self-hosted CAPTCHA on signup, login, forgot password; rate limits on signup, resend, reset

## Step 2 – Application form (each step saves as draft)
1. Basic details: name, DOB, gender (Male/Female/Transgender), family income, last school
2. Address: current and permanent (with "same as current"), state and district dropdowns, PIN, and "state you belong to"
3. Category: DU categories (UR, OBC-NCL, SC, ST, EWS) and Christian (CNI, CNID, COTH), CST checkbox, baptism details (date, church, priest's name...), PwBD yes/no with DU/RPwD disability groups, percentage, UDID
4. Class 10 and 12: board, subjects from NTA list (no free text), max marks, marks obtained, auto percentage
5. CUET (NTA): language and domain papers with marks; each paper must match a Class 12 subject (blocked if not)
6. Courses: only DU-eligible courses selectable; per course choose language + papers per DU rules, BFS/PCM, DU CSAS preference number; female applicants also give JMC preference for courses offered at both; residence option
7. Statements: why St. Stephen's, why each chosen course
8. Uploads: photo, signature, marksheets, CUET scorecard, CSAS preference page, conditional certificates (baptism, ST, category, PwBD); image/PDF only, type checked from contents, stored privately
9. Preview and final submit (locked afterwards)

## Step 3 – Fees and SBI ePay
- ₹500 first course + ₹200 each extra course + ₹1,000 residence (all editable by super admin)
- After payment: add course at ₹500 each, add residence at ₹1,000, each a separate transaction
- SBI ePay: encrypted request/response, server-side amount, status API double-check, duplicate/replay protection
- Payment tracking table, receipts, reconciliation and daily settlement report

## Step 4 – Admin portal
- Super admin: departments, staff accounts by role and department, per-account permissions, deactivate, audit log
- Roles: admin, department faculty, religious scrutiny, accounts, helpdesk/chat admin, print, interview panel, attendance desk (tech team)
- Master data: courses (seats per category, eligibility, CUET combinations, BFS/PCM, JMC switch), states/districts, categories, PwBD types, NTA subjects and equivalences, fees, document types
- Dashboard: registrations, submitted, paid; course-wise, category-wise, gender, state (by current/permanent/belongs-to), residence, PwBD, JMC vs SSC, payments, drop-off, scrutiny status, Excel export
- Academic scrutiny (department-wise, live queue once paid): academic mapping table (CUET ↔ Class 12 ↔ course rule), document checklist, approve/reject/query, locking
- Religious scrutiny (Christian categories): religious section and certificates only
- Queries: reviewer raises query → applicant email → reply via secure upload link (and optionally by email reply via IMAP) → attached to profile with history → back in queue; reminders
- Helpdesk: floating help button; chat with admin (logged in) and query form with name, email, mobile, screenshot; inbox, assignment, quick replies, reports
- Print role: application form, documents, receipts, call letters, bulk PDF; print log and footer
- Notices, bulk email/SMS, correction window, admission dates

## Step 5 – Interviews, merit and seats
- Eligible = paid + academic scrutiny approved + religious scrutiny approved (if Christian category)
- Call letters: panel, date, slot, venue/link, QR code; dashboard + email + SMS; reschedule/reissue
- Attendance desk marks present/absent (QR scan); interview panel login sees its list
- Interview marks: each member's score out of 15, system averages; department locks
- Final score: CUET (out of 85, from verified marks) + interview (out of 15) = 100; all three shown everywhere
- Merit lists course-wise and category-wise, toppers, ties (higher CUET, then Class 12 %, then older)
- Multiple selections resolved by DU CSAS preference; in other courses the row shows "Already selected – <course> (Pref n)" with actions disabled
- Seat allotment against seat matrix, offer letter, accept by deadline, lapse → next round
- Selected list dashboard category-wise with Excel export

## Open questions
- Religious scrutiny rejection: move to General automatically (default) or reject?
- PwBD fee concession? (default: none)
- Interview marks entered out of 15 (default) or out of 100 scaled?
- DU preference source: applicant-entered (default) plus optional DU file import?
- College mailbox (IMAP) for email-reply queries?
- List of courses common to JMC and St. Stephen's
- SBI ePay merchant credentials and test kit
